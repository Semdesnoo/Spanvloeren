<?php
// ══════════════════════════════════════════════════════════════════════════════
//  POST /admin/create-label.php
//  Maakt een MyParcel-zending aan en download het label als PDF.
// ══════════════════════════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../api/config.php';

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$orderId  = trim($_POST['order_id']      ?? '');
$carrier  = (int)($_POST['carrier']      ?? 1);   // 1=PostNL, 5=DHL
$pkgType  = (int)($_POST['package_type'] ?? 1);   // 1=Pakket, 2=Brievenbus

if (!in_array($carrier, [1, 5], true)) $carrier = 1;
if (!in_array($pkgType, [1, 2], true)) $pkgType = 1;

if (!$orderId) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Geen order-ID opgegeven']);
    exit;
}

// ── Bestelling ophalen ────────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try { $db->exec("ALTER TABLE orders ADD COLUMN myparcel_shipment_id TEXT DEFAULT ''"); } catch (Exception $_) {}
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Databasefout: ' . $e->getMessage()]);
    exit;
}

$s = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
$s->execute([$orderId]);
$order = $s->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Bestelling niet gevonden']);
    exit;
}

// ── Land → ISO-code ──────────────────────────────────────────────────────────
$countryMap = [
    'nederland'     => 'NL', 'netherlands'  => 'NL', 'nl' => 'NL',
    'belgie'        => 'BE', 'belgië'       => 'BE', 'belgium' => 'BE', 'be' => 'BE',
    'duitsland'     => 'DE', 'germany'      => 'DE', 'deutschland' => 'DE', 'de' => 'DE',
    'frankrijk'     => 'FR', 'france'       => 'FR', 'fr' => 'FR',
    'verenigd koninkrijk' => 'GB', 'uk' => 'GB', 'gb' => 'GB',
];
$cc = $countryMap[strtolower(trim($order['country']))] ?? 'NL';

// ── Zending aanmaken bij MyParcel ─────────────────────────────────────────────
$postcode = strtoupper(str_replace(' ', '', $order['postcode']));

$recipient = array_filter([
    'cc'          => $cc,
    'city'        => $order['city'],
    'street'      => $order['street'],
    'number'      => $order['number'],
    'postal_code' => $postcode,
    'person'      => $order['name'],
    'email'       => $order['email'] ?: null,
    'phone'       => $order['phone'] ?: null,
], fn($v) => $v !== null && $v !== '');

$payload = [
    'data' => [
        'shipments' => [[
            'recipient' => $recipient,
            'options'   => [
                'package_type'      => $pkgType,
                'label_description' => $orderId,
            ],
            'carrier' => $carrier,
        ]]
    ]
];

$authHeader = 'Authorization: basic ' . base64_encode(MYPARCEL_API_KEY);

$ch = curl_init('https://api.myparcel.nl/shipments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        $authHeader,
        'Content-Type: application/vnd.shipment+json;charset=utf-8',
        'Accept: application/json;charset=utf-8',
    ],
    CURLOPT_TIMEOUT => 30,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Verbindingsfout: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['data']['ids'][0]['id'])) {
    header('Content-Type: application/json');
    $errMsg = $data['message'] ?? ($data['errors'][0]['message'] ?? 'Onbekende fout');
    echo json_encode(['ok' => false, 'error' => 'MyParcel: ' . $errMsg, 'http' => $httpCode, 'raw' => $response]);
    exit;
}

$shipmentId = (string)$data['data']['ids'][0]['id'];

// Opslaan in database
$db->prepare("UPDATE orders SET myparcel_shipment_id=?, updated_at=CURRENT_TIMESTAMP WHERE order_id=?")
   ->execute([$shipmentId, $orderId]);

// ── Label ophalen (met retry) ─────────────────────────────────────────────────
$labelPdf  = null;
$labelCode = 0;

for ($try = 0; $try < 4; $try++) {
    sleep(2);
    $lch = curl_init('https://api.myparcel.nl/shipment_labels/' . $shipmentId . '?format=pdf&dpi=96');
    curl_setopt_array($lch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Accept: application/pdf',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $labelPdf  = curl_exec($lch);
    $labelCode = curl_getinfo($lch, CURLINFO_HTTP_CODE);
    curl_close($lch);
    if ($labelCode === 200 && strlen($labelPdf) > 1000) break;
    $labelPdf = null;
}

if (!$labelPdf) {
    // Label nog niet klaar — stuur ID terug zodat client opnieuw kan proberen
    header('Content-Type: application/json');
    echo json_encode([
        'ok'          => true,
        'pending'     => true,
        'shipment_id' => $shipmentId,
        'message'     => 'Zending aangemaakt (#' . $shipmentId . '). Label wordt nog gegenereerd — download het via de knop hieronder.',
    ]);
    exit;
}

// ── PDF terugsturen ───────────────────────────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="label-' . $orderId . '.pdf"');
header('Content-Length: ' . strlen($labelPdf));
echo $labelPdf;
