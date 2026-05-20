<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Mollie – Webhook handler
//  POST /api/webhook.php
//  Mollie roept dit aan zodra de betalingsstatus verandert.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

$paymentId = $_POST['id'] ?? '';
if (!$paymentId) {
    http_response_code(400);
    exit;
}

// ── Betaling ophalen bij Mollie ───────────────────────────────────────────────
$ch = curl_init('https://api.mollie.com/v2/payments/' . urlencode($paymentId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MOLLIE_API_KEY],
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
curl_close($ch);

$payment = json_decode($response, true);
$status  = $payment['status']  ?? 'unknown';
$amount  = $payment['amount']['value']  ?? '?';
$orderId = $payment['metadata']['order_id'] ?? $paymentId;
$customer = $payment['metadata']['customer'] ?? [];
$items    = $payment['metadata']['items']    ?? [];

// ── Logging ───────────────────────────────────────────────────────────────────
$logLine = date('Y-m-d H:i:s') . " | $orderId | Status: $status | Bedrag: €$amount\n";
file_put_contents(__DIR__ . '/payments.log', $logLine, FILE_APPEND | LOCK_EX);

// ── Opslaan in SQLite-database ────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id               INTEGER  PRIMARY KEY AUTOINCREMENT,
        order_id         TEXT     NOT NULL UNIQUE,
        payment_id       TEXT,
        status           TEXT     DEFAULT 'pending',
        amount           TEXT     DEFAULT '0.00',
        name             TEXT     DEFAULT '',
        email            TEXT     DEFAULT '',
        phone            TEXT     DEFAULT '',
        street           TEXT     DEFAULT '',
        number           TEXT     DEFAULT '',
        postcode         TEXT     DEFAULT '',
        city             TEXT     DEFAULT '',
        country          TEXT     DEFAULT '',
        notes            TEXT     DEFAULT '',
        items            TEXT     DEFAULT '[]',
        tracking_code    TEXT     DEFAULT '',
        tracking_carrier TEXT     DEFAULT '',
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_code TEXT DEFAULT ''"); } catch (Exception $_) {}
    try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_carrier TEXT DEFAULT ''"); } catch (Exception $_) {}
    $stmt = $db->prepare("
        INSERT INTO orders
            (order_id, payment_id, status, amount, name, email, phone,
             street, number, postcode, city, country, notes, items)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(order_id) DO UPDATE SET
            status     = excluded.status,
            payment_id = excluded.payment_id,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $orderId, $paymentId, $status, $amount,
        $customer['name']       ?? '',
        $customer['email']      ?? '',
        $customer['telefoon']   ?? '',
        $customer['straat']     ?? '',
        $customer['huisnummer'] ?? '',
        $customer['postcode']   ?? '',
        $customer['stad']       ?? '',
        $customer['land']       ?? '',
        $customer['notities']   ?? '',
        json_encode($items),
    ]);
} catch (Exception $e) {
    error_log('[Spanvloeren DB] ' . $e->getMessage());
}

// ── Push-melding via ntfy.sh bij betaalde bestelling ─────────────────────────
if ($status === 'paid' && defined('NTFY_TOPIC') && strpos(NTFY_TOPIC, 'KIES') === false) {
    $ntfyBody = "Nieuwe bestelling: $orderId\n" .
                "Klant: " . ($customer['name'] ?? '—') . "\n" .
                "Bedrag: €$amount\n" .
                "Stad: " . ($customer['stad'] ?? '—');
    $ntfyCh = curl_init('https://ntfy.sh/' . urlencode(NTFY_TOPIC));
    curl_setopt_array($ntfyCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $ntfyBody,
        CURLOPT_HTTPHEADER     => [
            'Title: 📦 Nieuwe bestelling!',
            'Priority: high',
            'Tags: package,moneybag',
            'Click: ' . SITE_URL . '/admin/?order=' . urlencode($orderId),
        ],
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ntfyCh);
    curl_close($ntfyCh);
}

// ── E-mails bij geslaagde betaling ───────────────────────────────────────────
if ($status === 'paid') {

    // Interne melding aan de webshop
    $itemLines = '';
    foreach ($items as $item) {
        $type = ($item['bundle'] ?? false) ? '6-Pack Bundel' : '1L';
        $itemLines .= $item['name'] . " ($type) x" . $item['qty'] . " = €" . number_format($item['linePrice'], 2, ',', '.') . "\n";
    }
    $internalBody =
        "BETAALDE BESTELLING\n==================\n\n" .
        "Order: $orderId\n" .
        "Betaling: $paymentId\n\n" .
        "PRODUCTEN\n---------\n$itemLines\n" .
        "Bedrag: €$amount\n\n" .
        "KLANTGEGEVENS\n-------------\n" .
        "Naam:     " . ($customer['name']     ?? '-') . "\n" .
        "E-mail:   " . ($customer['email']    ?? '-') . "\n" .
        "Telefoon: " . ($customer['telefoon'] ?? '-') . "\n\n" .
        "ADRES\n-----\n" .
        ($customer['straat'] ?? '') . ' ' . ($customer['huisnummer'] ?? '') . "\n" .
        ($customer['postcode'] ?? '') . ' ' . ($customer['stad'] ?? '') . "\n" .
        ($customer['land'] ?? '') . "\n\n" .
        "Opmerkingen: " . ($customer['notities'] ?? 'Geen') . "\n";
    mail(ORDER_EMAIL, 'Nieuwe betaalde bestelling – ' . $orderId, $internalBody, 'From: no-reply@spanvloeren.nl');

    // Bevestigingsmail naar klant via sjabloon
    mailSendOrderConfirmation($orderId, $customer, $items, $amount);

    // ── Automatisch verzendlabel aanmaken via MyParcel ────────────────────────
    if (isset($db)) {
        try { $db->exec("ALTER TABLE orders ADD COLUMN myparcel_shipment_id TEXT DEFAULT ''"); } catch (Exception $_) {}

        // Niet aanmaken als er al een zending-ID is (webhook kan meerdere keren vuren)
        $chk = $db->prepare("SELECT myparcel_shipment_id FROM orders WHERE order_id = ?");
        $chk->execute([$orderId]);
        $existingId = $chk->fetchColumn();

        if (!$existingId) {
            $countryMap = [
                'nederland' => 'NL', 'netherlands' => 'NL', 'nl' => 'NL',
                'belgie' => 'BE', 'belgië' => 'BE', 'belgium' => 'BE', 'be' => 'BE',
                'duitsland' => 'DE', 'germany' => 'DE', 'deutschland' => 'DE', 'de' => 'DE',
                'frankrijk' => 'FR', 'france' => 'FR', 'fr' => 'FR',
                'verenigd koninkrijk' => 'GB', 'uk' => 'GB', 'gb' => 'GB',
            ];
            $cc       = $countryMap[strtolower(trim($customer['land'] ?? 'nederland'))] ?? 'NL';
            $postcode = strtoupper(str_replace(' ', '', $customer['postcode'] ?? ''));

            $recipient = array_filter([
                'cc'          => $cc,
                'city'        => $customer['stad']       ?? '',
                'street'      => $customer['straat']     ?? '',
                'number'      => $customer['huisnummer'] ?? '',
                'postal_code' => $postcode,
                'person'      => $customer['name']       ?? '',
                'email'       => ($customer['email']     ?: null),
                'phone'       => ($customer['telefoon']  ?: null),
            ], fn($v) => $v !== null && $v !== '');

            $mpPayload = ['data' => ['shipments' => [[
                'recipient' => $recipient,
                'options'   => [
                    'package_type'      => AUTO_PACKAGE_TYPE,
                    'label_description' => $orderId,
                ],
                'carrier' => AUTO_CARRIER,
            ]]]];

            $mpCh = curl_init('https://api.myparcel.nl/shipments');
            curl_setopt_array($mpCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($mpPayload),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: basic ' . base64_encode(MYPARCEL_API_KEY),
                    'Content-Type: application/vnd.shipment+json;charset=utf-8',
                    'Accept: application/json;charset=utf-8',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            $mpResp = curl_exec($mpCh);
            $mpCode = curl_getinfo($mpCh, CURLINFO_HTTP_CODE);
            curl_close($mpCh);

            if ($mpCode === 200) {
                $mpData     = json_decode($mpResp, true);
                $shipmentId = (string)($mpData['data']['ids'][0]['id'] ?? '');
                if ($shipmentId) {
                    $db->prepare("UPDATE orders SET myparcel_shipment_id=?, updated_at=CURRENT_TIMESTAMP WHERE order_id=?")
                       ->execute([$shipmentId, $orderId]);
                    file_put_contents(__DIR__ . '/payments.log',
                        date('Y-m-d H:i:s') . " | $orderId | MyParcel zending aangemaakt: #$shipmentId\n",
                        FILE_APPEND | LOCK_EX);
                }
            } else {
                file_put_contents(__DIR__ . '/payments.log',
                    date('Y-m-d H:i:s') . " | $orderId | MyParcel fout: HTTP $mpCode – $mpResp\n",
                    FILE_APPEND | LOCK_EX);
            }
        }
    }
}

http_response_code(200);
echo 'OK';
