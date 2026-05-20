<?php
// GET /admin/download-label.php?id=SHIPMENT_ID&order=ORD-xxx
// Herdownload een bestaand MyParcel-label als PDF.
session_start();
require_once __DIR__ . '/../api/config.php';

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403); exit('Niet ingelogd');
}

$shipmentId = trim($_GET['id']    ?? '');
$orderId    = trim($_GET['order'] ?? 'label');

if (!$shipmentId || !ctype_digit($shipmentId)) {
    http_response_code(400); exit('Ongeldig zending-ID');
}

$authHeader = 'Authorization: basic ' . base64_encode(MYPARCEL_API_KEY);

$ch = curl_init('https://api.myparcel.nl/shipment_labels/' . $shipmentId . '?format=pdf&dpi=96');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => [$authHeader, 'Accept: application/pdf'],
    CURLOPT_TIMEOUT        => 20,
]);
$pdf      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || strlen($pdf) < 1000) {
    http_response_code(502);
    exit('Label nog niet beschikbaar. Probeer het over een moment opnieuw.');
}

$inline = !empty($_GET['print']);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="label-' . $orderId . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
