<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Admin – Testbestelling simuleren (alleen lokaal)
//  POST /admin/test-order.php
// ══════════════════════════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/mail.php';

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403); exit('Niet ingelogd');
}

$host    = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');

if (!$isLocal) {
    http_response_code(403); exit('Alleen beschikbaar in lokale omgeving');
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId  = 'ORD-' . date('Ymd') . '-TEST' . strtoupper(substr(uniqid(), -4));
    $amount   = '17.44';

    $customer = [
        'name'       => trim($_POST['name']      ?? 'Jan Jansen'),
        'email'      => trim($_POST['email']     ?? 'test@test.nl'),
        'telefoon'   => trim($_POST['phone']     ?? '0612345678'),
        'straat'     => trim($_POST['street']    ?? 'Teststraat'),
        'huisnummer' => trim($_POST['number']    ?? '12'),
        'postcode'   => trim($_POST['postcode']  ?? '1234AB'),
        'stad'       => trim($_POST['city']      ?? 'Amsterdam'),
        'land'       => trim($_POST['country']   ?? 'Nederland'),
        'notities'   => trim($_POST['notes']     ?? ''),
    ];

    $items = [[
        'name'      => 'Miral DapiDus-Reiniger 1L',
        'qty'       => 1,
        'linePrice' => 12.49,
        'bundle'    => false,
    ]];

    // ── Database opslaan ────────────────────────────────────────────────────────
    try {
        $db = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL UNIQUE,
            payment_id TEXT, status TEXT DEFAULT 'pending',
            amount TEXT DEFAULT '0.00', name TEXT DEFAULT '',
            email TEXT DEFAULT '', phone TEXT DEFAULT '',
            street TEXT DEFAULT '', number TEXT DEFAULT '',
            postcode TEXT DEFAULT '', city TEXT DEFAULT '',
            country TEXT DEFAULT '', notes TEXT DEFAULT '',
            items TEXT DEFAULT '[]', tracking_code TEXT DEFAULT '',
            tracking_carrier TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_code TEXT DEFAULT ''"); }        catch (Exception $_) {}
        try { $db->exec("ALTER TABLE orders ADD COLUMN tracking_carrier TEXT DEFAULT ''"); }      catch (Exception $_) {}
        try { $db->exec("ALTER TABLE orders ADD COLUMN myparcel_shipment_id TEXT DEFAULT ''"); } catch (Exception $_) {}

        $db->prepare("
            INSERT INTO orders (order_id, payment_id, status, amount, name, email, phone,
                                street, number, postcode, city, country, notes, items)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $orderId, 'tr_TEST_' . uniqid(), 'paid', $amount,
            $customer['name'], $customer['email'], $customer['telefoon'],
            $customer['straat'], $customer['huisnummer'], $customer['postcode'],
            $customer['stad'], $customer['land'], $customer['notities'],
            json_encode($items),
        ]);

        // ── Bevestigingsmail ────────────────────────────────────────────────────
        mailSendOrderConfirmation($orderId, $customer, $items, $amount);

        // ── MyParcel label aanmaken ─────────────────────────────────────────────
        $shipmentId = '';
        $countryMap = [
            'nederland' => 'NL', 'netherlands' => 'NL', 'nl' => 'NL',
            'belgie' => 'BE', 'belgië' => 'BE', 'be' => 'BE',
        ];
        $cc       = $countryMap[strtolower(trim($customer['land']))] ?? 'NL';
        $postcode = strtoupper(str_replace(' ', '', $customer['postcode']));

        $recipient = array_filter([
            'cc'          => $cc,
            'city'        => $customer['stad'],
            'street'      => $customer['straat'],
            'number'      => $customer['huisnummer'],
            'postal_code' => $postcode,
            'person'      => $customer['name'],
            'email'       => $customer['email'] ?: null,
            'phone'       => $customer['telefoon'] ?: null,
        ], fn($v) => $v !== null && $v !== '');

        $mpPayload = ['data' => ['shipments' => [[
            'recipient' => $recipient,
            'options'   => ['package_type' => AUTO_PACKAGE_TYPE, 'label_description' => $orderId],
            'carrier'   => AUTO_CARRIER,
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
            }
        }

        $result = ['ok' => true, 'order_id' => $orderId, 'shipment_id' => $shipmentId, 'mp_http' => $mpCode];

    } catch (Exception $e) {
        $result = ['ok' => false, 'error' => $e->getMessage()];
    }
}
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Testbestelling – Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f4f6fb;color:#111827;font-size:15px}
.wrap{max-width:560px;margin:48px auto;padding:0 20px}
h1{font-size:22px;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:#e63946;margin-bottom:6px}
.sub{font-size:13px;color:#6b7280;margin-bottom:28px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.07)}
label{display:block;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#6b7280;margin-bottom:6px;margin-top:16px}
label:first-of-type{margin-top:0}
input{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:14px;color:#111827;outline:none}
input:focus{border-color:#e63946}
.btn{display:block;width:100%;margin-top:24px;padding:13px;background:#e63946;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:1px}
.btn:hover{background:#c1121f}
.result{margin-top:20px;padding:16px 18px;border-radius:8px;font-size:14px;line-height:1.7}
.result--ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
.result--err{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}
.back{display:inline-block;margin-bottom:20px;font-size:13px;color:#6b7280;text-decoration:none}
.back:hover{color:#111827}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="index.php">&#8592; Terug naar dashboard</a>
  <h1>Testbestelling</h1>
  <p class="sub">Simuleert een betaalde bestelling — alleen beschikbaar op localhost.</p>

  <?php if ($result): ?>
    <?php if ($result['ok']): ?>
    <div class="result result--ok">
      <strong>✅ Testbestelling aangemaakt!</strong><br><br>
      Order-ID: <strong><?= htmlspecialchars($result['order_id']) ?></strong><br>
      <?php if ($result['shipment_id']): ?>
        MyParcel zending: <span class="badge">#<?= htmlspecialchars($result['shipment_id']) ?></span><br>
      <?php else: ?>
        MyParcel: <span style="color:#c2410c">⚠ Geen label aangemaakt (HTTP <?= (int)$result['mp_http'] ?>)</span><br>
      <?php endif; ?>
      <br>
      <a href="index.php" style="color:#15803d;font-weight:700">→ Bekijk in het dashboard</a>
    </div>
    <?php else: ?>
    <div class="result result--err">❌ <?= htmlspecialchars($result['error']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <form method="POST">
      <label>Naam</label>
      <input name="name"     value="Jan Jansen">
      <label>E-mailadres</label>
      <input name="email"    type="email" value="test@test.nl">
      <label>Telefoon</label>
      <input name="phone"    value="0612345678">
      <label>Straat</label>
      <input name="street"   value="Teststraat">
      <label>Huisnummer</label>
      <input name="number"   value="12">
      <label>Postcode</label>
      <input name="postcode" value="1234AB">
      <label>Stad</label>
      <input name="city"     value="Amsterdam">
      <button class="btn" type="submit">Betaalde bestelling simuleren</button>
    </form>
  </div>
</div>
</body>
</html>
