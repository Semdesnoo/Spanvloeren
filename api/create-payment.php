<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Mollie – Betaling aanmaken
//  POST /api/create-payment.php
//  Body (JSON): { amount, method, customer, items }
// ══════════════════════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// ── Alleen POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ── API-sleutel nog niet ingesteld? ──────────────────────────────────────────
if (strpos(MOLLIE_API_KEY, 'xxxx') !== false) {
    http_response_code(503);
    echo json_encode(['error' => 'Mollie API-sleutel is nog niet ingesteld in api/config.php']);
    exit;
}

// ── Input inlezen ─────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige aanvraag']);
    exit;
}

$amount = round(floatval($input['amount']), 2);
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig bedrag']);
    exit;
}

// ── Betaalmethode mappen ──────────────────────────────────────────────────────
$methodMap = [
    'iDEAL'               => 'ideal',
    'Bankoverschrijving'  => 'banktransfer',
    'Betaallink per e-mail' => null,   // Mollie toont alle opties
];
$mollieMethod = $methodMap[$input['method'] ?? ''] ?? null;

// ── Order-referentie ──────────────────────────────────────────────────────────
$orderRef = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

// ── Omschrijving opbouwen ─────────────────────────────────────────────────────
$customer = $input['customer'] ?? [];
$items    = $input['items'] ?? [];
$desc     = 'Miral – ' . ($customer['name'] ?? 'Bestelling') . ' [' . $orderRef . ']';

// ── Mollie API-aanroep ────────────────────────────────────────────────────────
// Lokaal testen: gebruik het echte request-host zodat redirect terugkomt op localhost
$host    = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');
$baseUrl = $isLocal ? 'http://' . $host . '/spanvloeren' : SITE_URL;

$payload = [
    'amount'      => ['currency' => 'EUR', 'value' => number_format($amount, 2, '.', '')],
    'description' => $desc,
    'redirectUrl' => $baseUrl . '/betaald.html?order=' . urlencode($orderRef),
    'cancelUrl'   => $baseUrl . '/checkout.html?geannuleerd=1',
    'webhookUrl'  => $isLocal ? 'https://webhook.site/test' : $baseUrl . '/api/webhook.php',
    'locale'      => 'nl_NL',
    'metadata'    => [
        'order_id' => $orderRef,
        'customer' => $customer,
        'items'    => $items,
    ],
];

if ($mollieMethod !== null) {
    $payload['method'] = $mollieMethod;
}

$ch = curl_init('https://api.mollie.com/v2/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . MOLLIE_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Verbindingsfout: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpStatus !== 201 || empty($data['_links']['checkout']['href'])) {
    http_response_code(502);
    error_log('[Mollie] HTTP ' . $httpStatus . ' – ' . $response);
    echo json_encode(['error' => 'Betaling aanmaken mislukt', 'detail' => $data['detail'] ?? '']);
    exit;
}

// ── Succes ────────────────────────────────────────────────────────────────────
echo json_encode([
    'checkoutUrl' => $data['_links']['checkout']['href'],
    'orderId'     => $orderRef,
    'paymentId'   => $data['id'],
]);
