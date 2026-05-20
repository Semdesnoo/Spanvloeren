<?php
// POST /admin/save-template.php – slaat een bewerkt e-mailsjabloon op.
session_start();
require_once __DIR__ . '/../api/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Niet ingelogd']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$allowed = ['order-confirmation', 'shipping-confirmation'];
$name    = $_POST['template'] ?? '';
$html    = $_POST['html']     ?? '';

if (!in_array($name, $allowed)) {
    echo json_encode(['ok' => false, 'error' => 'Ongeldig sjabloon']);
    exit;
}

if (strlen($html) < 50) {
    echo json_encode(['ok' => false, 'error' => 'Sjabloon is te kort']);
    exit;
}

$path   = __DIR__ . '/../api/templates/' . $name . '.html';
$result = file_put_contents($path, $html);

echo json_encode([
    'ok'    => $result !== false,
    'error' => $result === false ? 'Schrijven mislukt – controleer bestandsrechten' : null,
]);
