<?php
// ══════════════════════════════════════════════════════════════════════════════
//  POST /admin/send-tracking.php
//  Slaat het track & trace nummer op en stuurt een verzendbevestiging naar de klant.
// ══════════════════════════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/mail.php';

header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Niet ingelogd']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$orderId  = trim($_POST['order_id']  ?? '');
$tracking = trim($_POST['tracking']  ?? '');
$carrier  = in_array($_POST['carrier'] ?? '', ['postnl', 'dhl']) ? $_POST['carrier'] : 'postnl';

if (!$orderId || !$tracking) {
    echo json_encode(['ok' => false, 'error' => 'Vul een track & trace nummer in']);
    exit;
}

// ── Database ──────────────────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Kolommen toevoegen als ze nog niet bestaan
    try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_code TEXT DEFAULT ''"); }    catch (Exception $_) {}
    try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_carrier TEXT DEFAULT ''"); } catch (Exception $_) {}

    // Opslaan
    $db->prepare("UPDATE orders SET tracking_code=?, tracking_carrier=?, updated_at=CURRENT_TIMESTAMP WHERE order_id=?")
       ->execute([$tracking, $carrier, $orderId]);

    // Bestelling ophalen
    $s = $db->prepare("SELECT * FROM orders WHERE order_id=?");
    $s->execute([$orderId]);
    $order = $s->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Databasefout: ' . $e->getMessage()]);
    exit;
}

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Bestelling niet gevonden']);
    exit;
}

$customerEmail = $order['email'] ?? '';
if (!$customerEmail) {
    echo json_encode(['ok' => false, 'error' => 'Geen e-mailadres voor deze bestelling']);
    exit;
}

// ── Versturen via sjabloon ────────────────────────────────────────────────────
$sent = mailSendShippingConfirmation($order, $tracking, $carrier);
$carrierName = $carrier === 'dhl' ? 'DHL' : 'PostNL';

echo json_encode([
    'ok'      => $sent,
    'error'   => $sent ? null : 'Mail kon niet worden verstuurd (controleer de mailconfiguratie van de server)',
    'carrier' => $carrierName,
    'email'   => $customerEmail,
]);
