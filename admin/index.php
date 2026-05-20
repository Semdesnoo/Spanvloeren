<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Spanvloeren.nl – Admin Dashboard
//  Toegang: /admin/  →  inloggen met ADMIN_PASSWORD uit api/config.php
// ══════════════════════════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../api/config.php';

$pageError = '';

// Uitloggen
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Inloggen
if (!empty($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['sv_admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $pageError = 'Onjuist wachtwoord. Probeer het opnieuw.';
}

$loggedIn = $_SESSION['sv_admin'] ?? false;
$page     = $loggedIn ? ($_GET['page'] ?? 'orders') : 'login';

// ── Database ──────────────────────────────────────────────────────────────────
function getDb(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE        => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
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
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_code TEXT DEFAULT ''"); }        catch (Exception $_) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_carrier TEXT DEFAULT ''"); }      catch (Exception $_) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN myparcel_shipment_id TEXT DEFAULT ''"); } catch (Exception $_) {}
    return $pdo;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function statusBadge(string $s): string {
    $map = [
        'paid'     => ['#16a34a', 'Betaald'],
        'pending'  => ['#b45309', 'In behandeling'],
        'open'     => ['#b45309', 'Open'],
        'failed'   => ['#dc2626', 'Mislukt'],
        'canceled' => ['#dc2626', 'Geannuleerd'],
        'expired'  => ['#4b5563', 'Verlopen'],
    ];
    [$bg, $label] = $map[$s] ?? ['#4b5563', h($s)];
    return '<span class="badge" style="background:' . $bg . '">' . $label . '</span>';
}

function fmtDate(string $d): string {
    return date('d-m-Y H:i', strtotime($d));
}

function fmtAmount(string $a): string {
    return '&euro;&nbsp;' . number_format((float)$a, 2, ',', '.');
}

// ── Data ophalen (alleen als ingelogd) ────────────────────────────────────────
$stats  = null;
$orders = [];
$detail = null;
$filter = '';
$search = '';

if ($loggedIn) {
    $db = getDb();

    // Statistieken
    $stats = $db->query("
        SELECT
            COUNT(*)                                                                AS total,
            SUM(status = 'paid')                                                   AS paid,
            SUM(status IN ('pending','open'))                                      AS open_count,
            SUM(status IN ('failed','canceled','expired'))                         AS failed,
            ROUND(SUM(CASE WHEN status='paid' THEN CAST(amount AS REAL) ELSE 0 END), 2) AS revenue,
            ROUND(SUM(CASE WHEN status='paid' AND date(created_at) = date('now')
                           THEN CAST(amount AS REAL) ELSE 0 END), 2)              AS today
        FROM orders
    ")->fetch();

    // Detail
    if (!empty($_GET['order'])) {
        $s = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
        $s->execute([trim($_GET['order'])]);
        $detail = $s->fetch();
        if ($detail) {
            $detail['items_arr'] = json_decode($detail['items'] ?: '[]', true) ?: [];
        }
    }

    // Lijst
    if (!$detail) {
        $filter = in_array($_GET['status'] ?? '', ['paid','pending','open','failed','canceled','expired'])
                  ? $_GET['status'] : '';
        $search = trim($_GET['q'] ?? '');

        $where  = [];
        $params = [];
        if ($filter) {
            $where[]  = 'status = ?';
            $params[] = $filter;
        }
        if ($search) {
            $where[]  = '(order_id LIKE ? OR name LIKE ? OR email LIKE ? OR city LIKE ?)';
            $like     = "%$search%";
            array_push($params, $like, $like, $like, $like);
        }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT * FROM orders $whereSQL ORDER BY created_at DESC LIMIT 300");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
    }

    // MyParcel verbindingsstatus (alleen overzichtspagina)
    $mpStatus = null;
    if (!$detail && $page !== 'templates') {
        $mpStatus = ['ok' => false, 'name' => '', 'shop' => '', 'billing_ok' => true, 'error' => ''];
        $mpCh = curl_init('https://api.myparcel.nl/accounts');
        curl_setopt_array($mpCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: basic ' . base64_encode(MYPARCEL_API_KEY),
                'Accept: application/json;charset=utf-8',
            ],
            CURLOPT_TIMEOUT => 5,
        ]);
        $mpResp = curl_exec($mpCh);
        $mpCode = curl_getinfo($mpCh, CURLINFO_HTTP_CODE);
        curl_close($mpCh);
        if ($mpCode === 200) {
            $mpData    = json_decode($mpResp, true);
            $mpAccount = $mpData['data']['accounts'][0] ?? null;
            if ($mpAccount) {
                $mpStatus['ok']         = true;
                $mpStatus['name']       = trim(($mpAccount['first_name'] ?? '') . ' ' . ($mpAccount['last_name'] ?? ''));
                $mpStatus['shop']       = $mpAccount['shops'][0]['name'] ?? '';
                $txStatus               = $mpAccount['shops'][0]['subscription_fee']['transaction_status'] ?? '';
                $mpStatus['billing_ok'] = ($txStatus !== 'unpaid');
            }
        } else {
            $mpStatus['error'] = 'Verbindingsfout (HTTP ' . (int)$mpCode . ')';
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  HTML
// ─────────────────────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Spanvloeren.nl</title>
<link rel="manifest" href="/admin/manifest.json">
<meta name="theme-color" content="#e63946">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Spanvloeren">
<link rel="apple-touch-icon" href="/admin/icon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700;900&display=swap" rel="stylesheet">
<style>
/* ── Reset & tokens ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --red:#e63946;--red-dark:#c1121f;
  --bg:#f4f6fb;--surface:#ffffff;--surface2:#f4f6fb;--surface3:#eaecf3;
  --border:#e5e7eb;--text:#111827;--muted:#6b7280;--green:#16a34a;
  --shadow:0 1px 4px rgba(0,0,0,.07);--shadow-md:0 4px 16px rgba(0,0,0,.08);
  --font:'Barlow',sans-serif;--font-cond:'Barlow Condensed',sans-serif;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--font);font-size:15px;line-height:1.5}
a{color:inherit;text-decoration:none}
button,input{font-family:inherit}

/* ── Layout ── */
.layout{display:flex;flex-direction:column;min-height:100vh}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 28px;height:64px;background:#fff;border-bottom:1px solid var(--border);box-shadow:var(--shadow);gap:16px;position:sticky;top:0;z-index:10}
.topbar__logo{font-family:var(--font-cond);font-size:20px;font-weight:900;letter-spacing:1px;text-transform:uppercase;color:var(--red)}
.topbar__sub{font-size:12px;color:var(--muted);margin-top:1px}
.main{flex:1;padding:28px;max-width:1200px;width:100%;margin:0 auto}

/* ── Stats ── */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:20px 24px;box-shadow:var(--shadow)}
.stat-card__label{font-size:12px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.stat-card__value{font-family:var(--font-cond);font-size:34px;font-weight:900;line-height:1;color:var(--text)}
.stat-card--red .stat-card__value{color:var(--red)}
.stat-card--green .stat-card__value{color:var(--green)}

/* ── Toolbar ── */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:340px}
.search-wrap input{width:100%;padding:9px 14px 9px 38px;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:14px;outline:none;transition:border-color .15s;box-shadow:var(--shadow)}
.search-wrap input:focus{border-color:var(--red)}
.search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;pointer-events:none}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.filter-tab{padding:7px 14px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-weight:600;background:#fff;color:var(--muted);cursor:pointer;transition:all .15s;box-shadow:var(--shadow)}
.filter-tab:hover,.filter-tab.active{background:var(--red);border-color:var(--red);color:#fff}
.filter-tab.active{cursor:default}

/* ── Tabel ── */
.table-wrap{background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:var(--shadow-md)}
.table-count{padding:14px 20px;font-size:13px;color:var(--muted);border-bottom:1px solid var(--border);background:#fff}
table{width:100%;border-collapse:collapse}
th{padding:13px 18px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);text-align:left;background:var(--surface2)}
td{padding:15px 18px;font-size:14px;border-top:1px solid var(--border)}
tr.order-row{cursor:pointer;transition:background .12s}
tr.order-row:hover td{background:#fafbff}
.order-id{font-family:monospace;font-size:13px;color:var(--muted)}
.name-cell{font-weight:600}
.email-cell{font-size:13px;color:var(--muted)}
.amount-cell{font-family:var(--font-cond);font-size:17px;font-weight:700}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}

/* ── Badge ── */
.badge{display:inline-block;padding:3px 11px;border-radius:20px;font-size:12px;font-weight:700;color:#fff;white-space:nowrap}

/* ── Detail ── */
.detail-back{display:inline-flex;align-items:center;gap:8px;margin-bottom:24px;padding:8px 16px;background:#fff;border:1px solid var(--border);border-radius:6px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;transition:color .15s;box-shadow:var(--shadow)}
.detail-back:hover{color:var(--text)}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:640px){.detail-grid{grid-template-columns:1fr}}
.detail-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;box-shadow:var(--shadow)}
.detail-card h3{font-family:var(--font-cond);font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:16px}
.detail-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px}
.detail-row:last-child{border-bottom:none}
.detail-row__label{color:var(--muted)}
.detail-row__value{font-weight:600;text-align:right;max-width:60%}
.detail-items{margin-top:4px}
.detail-item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px;gap:12px}
.detail-item:last-child{border-bottom:none}
.detail-item__name{flex:1}
.detail-item__price{font-weight:700;font-family:var(--font-cond);font-size:16px;white-space:nowrap}
.detail-total{display:flex;justify-content:space-between;padding:14px 0 0;font-weight:700;font-size:16px;margin-top:4px}
.detail-total span:last-child{font-family:var(--font-cond);font-size:20px;color:var(--red)}

/* ── Login ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--bg)}
.login-card{width:100%;max-width:360px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:40px;box-shadow:var(--shadow-md)}
.login-logo{font-family:var(--font-cond);font-size:26px;font-weight:900;text-transform:uppercase;color:var(--red);margin-bottom:4px;letter-spacing:1px}
.login-sub{font-size:14px;color:var(--muted);margin-bottom:28px}
.form-label{display:block;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.form-input{width:100%;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:15px;outline:none;transition:border-color .15s}
.form-input:focus{border-color:var(--red)}
.btn-submit{width:100%;margin-top:20px;padding:13px;background:var(--red);color:#fff;border:none;border-radius:6px;font-family:var(--font-cond);font-size:16px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;transition:background .15s}
.btn-submit:hover{background:var(--red-dark)}
.form-error{margin-top:14px;padding:10px 14px;background:#fff1f2;border:1px solid #fecdd3;border-radius:6px;font-size:13px;color:#be123c}

/* ── Navigatie ── */
.topnav{display:flex;gap:4px}
.topnav__link{padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;color:var(--muted);transition:all .15s}
.topnav__link:hover{background:var(--bg);color:var(--text)}
.topnav__link.active{background:var(--bg);color:var(--text);font-weight:700}

/* ── Sjabloon editor ── */
.tpl-wrap{display:grid;grid-template-columns:1fr 1fr;gap:0;background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:var(--shadow-md);height:calc(100vh - 220px);min-height:500px}
@media(max-width:900px){.tpl-wrap{grid-template-columns:1fr;height:auto}}
.tpl-editor{display:flex;flex-direction:column;border-right:1px solid var(--border)}
.tpl-preview{display:flex;flex-direction:column}
.tpl-panel-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);background:var(--bg);gap:12px;flex-shrink:0}
.tpl-panel-title{font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted)}
.tpl-textarea{flex:1;resize:none;border:none;outline:none;padding:16px 18px;font-family:'Courier New',monospace;font-size:13px;line-height:1.6;color:var(--text);background:#fff;width:100%}
.tpl-iframe{flex:1;border:none;width:100%;background:#f4f6fb}
.tpl-tabs{display:flex;gap:8px;margin-bottom:20px}
.tpl-tab{padding:9px 20px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-weight:600;background:#fff;color:var(--muted);cursor:pointer;transition:all .15s;box-shadow:var(--shadow)}
.tpl-tab.active{background:var(--red);border-color:var(--red);color:#fff}
.tpl-vars{background:#fff;border:1px solid var(--border);border-radius:10px;padding:18px 20px;margin-top:16px;box-shadow:var(--shadow)}
.tpl-vars h4{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
.tpl-var-grid{display:flex;flex-wrap:wrap;gap:8px}
.tpl-var{font-family:monospace;font-size:12px;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:4px 10px;color:var(--red);cursor:pointer;transition:background .12s}
.tpl-var:hover{background:var(--surface3)}
.btn-save{padding:8px 18px;background:var(--red);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s}
.btn-save:hover{background:var(--red-dark)}
.btn-save:disabled{background:var(--muted);cursor:default}
.save-msg{font-size:13px;margin-left:10px}

/* ── Logout button ── */
.btn-logout{padding:7px 16px;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;box-shadow:var(--shadow)}
.btn-logout:hover{border-color:var(--red);color:var(--red)}

/* ── Ntfy setup card ── */
.setup-card{display:flex;align-items:flex-start;gap:16px;background:rgba(230,57,70,.08);border:1px solid rgba(230,57,70,.3);border-radius:10px;padding:18px 20px;margin-bottom:24px}
.setup-card__icon{font-size:28px;flex-shrink:0;margin-top:2px}
.setup-card__body{flex:1}
.setup-card__title{font-family:var(--font-cond);font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--red);margin-bottom:6px}
.setup-card__text{font-size:14px;color:var(--muted);line-height:1.6}
.setup-card__topic{font-family:monospace;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:8px 14px;font-size:14px;color:var(--text);margin:10px 0;display:inline-block;word-break:break-all}
.setup-card__steps{margin:8px 0 0;padding-left:18px;font-size:14px;color:var(--muted);line-height:1.8}
.setup-card__close{background:transparent;border:none;color:var(--muted);font-size:18px;cursor:pointer;padding:4px;line-height:1;flex-shrink:0;transition:color .15s}
.setup-card__close:hover{color:var(--text)}

/* ── Shipping section ── */
.ship-address{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:16px 18px;font-size:15px;line-height:1.9;font-weight:500;margin-bottom:16px}
.ship-buttons{display:flex;gap:10px;flex-wrap:wrap}
.btn-ship{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;transition:all .15s;border:none;text-decoration:none}
.btn-ship--copy{background:var(--surface3);color:var(--text);border:1px solid var(--border)}
.btn-ship--copy:hover{background:var(--surface2);border-color:var(--muted)}
.btn-ship--postnl{background:#ff6200;color:#fff}
.btn-ship--postnl:hover{background:#e55700}
.btn-ship--dhl{background:#ffcc00;color:#000}
.btn-ship--dhl:hover{background:#e6b800}
.copied-msg{font-size:13px;color:var(--green);margin-left:8px;opacity:0;transition:opacity .3s}

/* ── Label knoppen in tabel ── */
.label-btn{padding:5px 12px;border:none;border-radius:5px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap}
.label-btn--print{background:#16a34a;color:#fff}
.label-btn--print:hover{background:#15803d}
.label-btn--create{background:var(--red);color:#fff}
.label-btn--create:hover{background:var(--red-dark)}
.label-btn:disabled{opacity:.5;cursor:default}

/* ── MyParcel status card ── */
.mp-card{display:flex;align-items:center;gap:16px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:18px 22px;margin-bottom:24px;box-shadow:var(--shadow);flex-wrap:wrap}
.mp-card__icon{font-size:26px;flex-shrink:0}
.mp-card__body{flex:1;min-width:0}
.mp-card__title{font-family:var(--font-cond);font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text);margin-bottom:3px}
.mp-card__sub{font-size:13px;color:var(--muted)}
.mp-card__badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.mp-card__badge--green{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.mp-card__badge--red{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
.mp-card__badge--orange{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.mp-card__actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-left:auto}
.mp-card__btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:all .15s}
.mp-card__btn--primary{background:var(--red);color:#fff}
.mp-card__btn--primary:hover{background:var(--red-dark)}
.mp-card__btn--ghost{background:#fff;color:var(--text);border:1px solid var(--border)}
.mp-card__btn--ghost:hover{border-color:var(--muted)}
</style>

</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ══════════ LOGIN ══════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">Spanvloeren</div>
    <div class="login-sub">Admin &ndash; Bestellingenbeheer</div>
    <form method="POST" action="">
      <label class="form-label" for="pw">Wachtwoord</label>
      <input class="form-input" type="password" name="password" id="pw" autofocus autocomplete="current-password" required>
      <button class="btn-submit" type="submit">Inloggen</button>
      <?php if ($pageError): ?>
        <div class="form-error"><?= h($pageError) ?></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php elseif ($detail): ?>
<!-- ══════════ DETAIL ══════════ -->
<div class="layout">
  <div class="topbar">
    <div>
      <div class="topbar__logo">Spanvloeren Admin</div>
      <div class="topbar__sub">Bestellingenbeheer</div>
    </div>
    <form method="POST"><button class="btn-logout" type="submit" name="logout">Uitloggen</button></form>
  </div>
  <div class="main">
    <a class="detail-back" href="<?= h($_SERVER['PHP_SELF']) ?>">&#8592; Terug naar overzicht</a>

    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap">
      <h1 style="font-family:var(--font-cond);font-size:28px;font-weight:900;text-transform:uppercase">
        <?= h($detail['order_id']) ?>
      </h1>
      <?= statusBadge($detail['status']) ?>
      <span style="color:var(--muted);font-size:14px"><?= fmtDate($detail['created_at']) ?></span>
    </div>

    <div class="detail-grid">
      <!-- Klantgegevens -->
      <div class="detail-card">
        <h3>Klantgegevens</h3>
        <div class="detail-row"><span class="detail-row__label">Naam</span><span class="detail-row__value"><?= h($detail['name'] ?: '—') ?></span></div>
        <div class="detail-row"><span class="detail-row__label">E-mail</span><span class="detail-row__value"><a href="mailto:<?= h($detail['email']) ?>" style="color:var(--red)"><?= h($detail['email'] ?: '—') ?></a></span></div>
        <div class="detail-row"><span class="detail-row__label">Telefoon</span><span class="detail-row__value"><?= h($detail['phone'] ?: '—') ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Adres</span><span class="detail-row__value"><?= h(trim($detail['street'] . ' ' . $detail['number']) ?: '—') ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Postcode & stad</span><span class="detail-row__value"><?= h(trim($detail['postcode'] . ' ' . $detail['city']) ?: '—') ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Land</span><span class="detail-row__value"><?= h($detail['country'] ?: '—') ?></span></div>
        <?php if ($detail['notes']): ?>
        <div class="detail-row"><span class="detail-row__label">Opmerkingen</span><span class="detail-row__value"><?= h($detail['notes']) ?></span></div>
        <?php endif; ?>
      </div>

      <!-- Betaling -->
      <div class="detail-card">
        <h3>Betaling</h3>
        <div class="detail-row"><span class="detail-row__label">Status</span><span class="detail-row__value"><?= statusBadge($detail['status']) ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Bedrag</span><span class="detail-row__value" style="font-family:var(--font-cond);font-size:20px;color:var(--red)"><?= fmtAmount($detail['amount']) ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Payment ID</span><span class="detail-row__value" style="font-family:monospace;font-size:12px;color:var(--muted)"><?= h($detail['payment_id'] ?: '—') ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Besteld op</span><span class="detail-row__value"><?= fmtDate($detail['created_at']) ?></span></div>
        <div class="detail-row"><span class="detail-row__label">Bijgewerkt op</span><span class="detail-row__value"><?= fmtDate($detail['updated_at']) ?></span></div>
      </div>

      <!-- Verzending -->
      <div class="detail-card" style="grid-column:1/-1">
        <h3>Verzending</h3>
        <?php
          $shipName    = h($detail['name'] ?: '—');
          $shipStreet  = h(trim($detail['street'] . ' ' . $detail['number']));
          $shipPc      = h(trim($detail['postcode'] . ' ' . $detail['city']));
          $shipCountry = h($detail['country'] ?: '');
          $shipFull    = strip_tags("$shipName\n$shipStreet\n$shipPc\n$shipCountry");
          $existingCode    = h($detail['tracking_code']    ?? '');
          $existingCarrier = $detail['tracking_carrier'] ?? 'postnl';
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start" class="ship-grid">
          <!-- Adres -->
          <div>
            <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:10px">Bezorgadres</div>
            <div class="ship-address">
              <?= $shipName ?><br><?= $shipStreet ?><br><?= $shipPc ?>
              <?php if ($shipCountry): ?><br><?= $shipCountry ?><?php endif; ?>
            </div>
            <div class="ship-buttons">
              <button class="btn-ship btn-ship--copy" onclick="copyAddress()">📋 Kopieer adres</button>
              <a class="btn-ship btn-ship--postnl" href="https://mijn.postnl.nl/bestellingenbeheer" target="_blank" rel="noopener">PostNL ↗</a>
              <a class="btn-ship btn-ship--dhl" href="https://mycustomer.dhl.com/en/nl/shipping.html" target="_blank" rel="noopener">DHL ↗</a>
              <span class="copied-msg" id="copiedMsg">Gekopieerd!</span>
            </div>
          </div>

          <!-- Track & trace invoer -->
          <div>
            <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:10px">Track &amp; trace nummer</div>
            <?php if ($existingCode): ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin-bottom:14px">
              <div style="font-size:12px;color:#15803d;font-weight:700;margin-bottom:4px">✅ Verzendbevestiging verstuurd</div>
              <div style="font-family:monospace;font-size:16px;font-weight:700;color:#111827"><?= $existingCode ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= strtoupper($existingCarrier) ?></div>
            </div>
            <?php endif; ?>
            <div style="display:flex;flex-direction:column;gap:10px">
              <select id="carrierSel" style="padding:9px 12px;border:1px solid var(--border);border-radius:6px;background:#fff;font-size:14px;color:var(--text);outline:none">
                <option value="postnl" <?= $existingCarrier==='postnl'?'selected':'' ?>>PostNL</option>
                <option value="dhl"    <?= $existingCarrier==='dhl'   ?'selected':'' ?>>DHL</option>
              </select>
              <input id="trackingInput" type="text" placeholder="Bijv. 3SDEVC123456789 of JD123456789" value="<?= $existingCode ?>"
                style="padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;color:var(--text);outline:none;font-family:monospace">
              <button id="sendTrackingBtn" onclick="sendTracking()" class="btn-ship" style="background:var(--red);color:#fff;justify-content:center;padding:11px 18px;font-size:14px">
                📧 Opslaan &amp; mail versturen naar klant
              </button>
              <div id="trackingMsg" style="font-size:13px;display:none;padding:10px 14px;border-radius:6px"></div>
            </div>
          </div>
        </div>
        <style>@media(max-width:640px){.ship-grid{grid-template-columns:1fr!important}}</style>
        <script>
        function copyAddress() {
          navigator.clipboard.writeText(<?= json_encode($shipFull) ?>).then(function() {
            var msg = document.getElementById('copiedMsg');
            msg.style.opacity = '1';
            setTimeout(function(){ msg.style.opacity = '0'; }, 2000);
          });
        }
        function sendTracking() {
          var code = document.getElementById('trackingInput').value.trim();
          var carrier = document.getElementById('carrierSel').value;
          var btn = document.getElementById('sendTrackingBtn');
          var msgEl = document.getElementById('trackingMsg');
          if (!code) { alert('Vul eerst een track & trace nummer in.'); return; }
          btn.disabled = true;
          btn.textContent = 'Bezig…';
          var fd = new FormData();
          fd.append('order_id', <?= json_encode($detail['order_id']) ?>);
          fd.append('tracking', code);
          fd.append('carrier', carrier);
          fetch('/admin/send-tracking.php', { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
              msgEl.style.display = 'block';
              if (data.ok) {
                msgEl.style.background = '#f0fdf4';
                msgEl.style.border = '1px solid #bbf7d0';
                msgEl.style.color = '#15803d';
                msgEl.textContent = '✅ Mail verstuurd naar ' + data.email;
                btn.textContent = '✅ Verstuurd';
              } else {
                msgEl.style.background = '#fff1f2';
                msgEl.style.border = '1px solid #fecdd3';
                msgEl.style.color = '#be123c';
                msgEl.textContent = '❌ ' + (data.error || 'Onbekende fout');
                btn.disabled = false;
                btn.textContent = '📧 Opslaan & mail versturen naar klant';
              }
            })
            .catch(function() {
              msgEl.style.display = 'block';
              msgEl.style.background = '#fff1f2';
              msgEl.style.color = '#be123c';
              msgEl.textContent = '❌ Verbindingsfout, probeer opnieuw';
              btn.disabled = false;
              btn.textContent = '📧 Opslaan & mail versturen naar klant';
            });
        }
        </script>
      </div>

      <!-- MyParcel label -->
      <div class="detail-card" style="grid-column:1/-1">
        <h3>Verzendlabel aanmaken</h3>
        <?php
          $shipmentId = $detail['myparcel_shipment_id'] ?? '';
          try { getDb()->exec("ALTER TABLE orders ADD COLUMN myparcel_shipment_id TEXT DEFAULT ''"); } catch(Exception $_){}
        ?>
        <?php if ($shipmentId): ?>
        <div style="display:flex;align-items:center;gap:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin-bottom:16px;flex-wrap:wrap">
          <span style="font-size:15px">✅</span>
          <div>
            <div style="font-size:13px;font-weight:700;color:#15803d">Zending aangemaakt bij MyParcel</div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">Zending-ID: <span style="font-family:monospace"><?= h($shipmentId) ?></span></div>
          </div>
          <a href="download-label.php?id=<?= urlencode($shipmentId) ?>&order=<?= urlencode($detail['order_id']) ?>"
             style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#15803d;color:#fff;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none">
            ↓ Download label
          </a>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
          <div>
            <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;letter-spacing:1px;text-transform:uppercase">Carrier</div>
            <select id="labelCarrier" style="padding:9px 12px;border:1px solid var(--border);border-radius:6px;background:#fff;font-size:14px;color:var(--text);outline:none">
              <option value="1">PostNL</option>
              <option value="5">DHL For You</option>
            </select>
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;letter-spacing:1px;text-transform:uppercase">Type</div>
            <select id="labelPkgType" style="padding:9px 12px;border:1px solid var(--border);border-radius:6px;background:#fff;font-size:14px;color:var(--text);outline:none">
              <option value="1">Pakket</option>
              <option value="2">Brievenbus</option>
            </select>
          </div>
          <button id="labelBtn" onclick="createLabel()"
            style="padding:10px 20px;background:var(--red);color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px">
            📦 Maak label en download
          </button>
        </div>
        <div id="labelMsg" style="display:none;margin-top:14px;padding:12px 16px;border-radius:8px;font-size:14px"></div>

        <script>
        function createLabel() {
          var btn = document.getElementById('labelBtn');
          var msg = document.getElementById('labelMsg');
          var carrier  = document.getElementById('labelCarrier').value;
          var pkgType  = document.getElementById('labelPkgType').value;

          btn.disabled    = true;
          btn.textContent = '⏳ Bezig…';
          msg.style.display = 'none';

          var fd = new FormData();
          fd.append('order_id',     <?= json_encode($detail['order_id']) ?>);
          fd.append('carrier',      carrier);
          fd.append('package_type', pkgType);

          fetch('create-label.php', { method: 'POST', body: fd })
            .then(function(r) {
              // Controleer of het een PDF of JSON is
              var ct = r.headers.get('Content-Type') || '';
              if (ct.indexOf('pdf') !== -1) {
                return r.blob().then(function(blob) {
                  var url = URL.createObjectURL(blob);
                  var a   = document.createElement('a');
                  a.href  = url;
                  a.download = 'label-' + <?= json_encode($detail['order_id']) ?> + '.pdf';
                  a.click();
                  URL.revokeObjectURL(url);
                  showMsg('✅ Label gedownload! Ververs de pagina om het zending-ID te zien.', 'green');
                  btn.disabled    = false;
                  btn.textContent = '📦 Maak label en download';
                });
              }
              return r.json().then(function(data) {
                if (data.pending) {
                  showMsg('⏳ ' + data.message + ' <a href="download-label.php?id=' + data.shipment_id + '&order=' + encodeURIComponent(<?= json_encode($detail['order_id']) ?>) + '" style="color:var(--red);font-weight:700">→ Download label</a>', 'blue');
                } else {
                  showMsg('❌ ' + (data.error || 'Onbekende fout'), 'red');
                }
                btn.disabled    = false;
                btn.textContent = '📦 Maak label en download';
              });
            })
            .catch(function() {
              showMsg('❌ Verbindingsfout, probeer opnieuw', 'red');
              btn.disabled    = false;
              btn.textContent = '📦 Maak label en download';
            });
        }
        function showMsg(html, color) {
          var msg = document.getElementById('labelMsg');
          var colors = {
            green: ['#f0fdf4','#bbf7d0','#15803d'],
            red:   ['#fff1f2','#fecdd3','#be123c'],
            blue:  ['#eff6ff','#bfdbfe','#1d4ed8'],
          };
          var c = colors[color] || colors.red;
          msg.style.display    = 'block';
          msg.style.background = c[0];
          msg.style.border     = '1px solid ' + c[1];
          msg.style.color      = c[2];
          msg.innerHTML        = html;
        }
        </script>
      </div>

      <!-- Producten -->
      <div class="detail-card" style="grid-column:1/-1">
        <h3>Bestelde producten</h3>
        <?php if ($detail['items_arr']): ?>
          <div class="detail-items">
            <?php foreach ($detail['items_arr'] as $item): ?>
              <div class="detail-item">
                <span class="detail-item__name">
                  <?= h($item['name'] ?? '—') ?>
                  <?php if (!empty($item['bundle'])): ?><span style="background:var(--surface3);border-radius:4px;padding:2px 8px;font-size:11px;margin-left:6px;color:var(--muted)">6-Pack</span><?php endif; ?>
                  <span style="color:var(--muted);font-size:13px;margin-left:8px">x<?= (int)($item['qty'] ?? 1) ?></span>
                </span>
                <span class="detail-item__price">&euro; <?= number_format((float)($item['linePrice'] ?? 0), 2, ',', '.') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="detail-total"><span>Totaal</span><span><?= fmtAmount($detail['amount']) ?></span></div>
        <?php else: ?>
          <p style="color:var(--muted);font-size:14px">Geen productdetails beschikbaar.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════ OVERZICHT ══════════ -->
<div class="layout">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:24px">
      <div>
        <div class="topbar__logo">Spanvloeren Admin</div>
      </div>
      <nav class="topnav">
        <a class="topnav__link <?= $page==='orders'?'active':'' ?>" href="<?= h($_SERVER['PHP_SELF']) ?>">Bestellingen</a>
        <a class="topnav__link <?= $page==='templates'?'active':'' ?>" href="<?= h($_SERVER['PHP_SELF']) ?>?page=templates">E-mailsjablonen</a>
      </nav>
    </div>
    <form method="POST"><button class="btn-logout" type="submit" name="logout">Uitloggen</button></form>
  </div>
  <div class="main">

    <?php if ($page === 'templates'): ?>
    <!-- ══════════ SJABLONEN ══════════ -->
    <?php
      $tplNames = [
        'order-confirmation'   => 'Orderbevestiging',
        'shipping-confirmation'=> 'Verzendbevestiging (T&T)',
      ];
      $activeTpl = in_array($_GET['tpl'] ?? '', array_keys($tplNames)) ? $_GET['tpl'] : 'order-confirmation';
      $tplVars = [
        'order-confirmation'    => ['{{name}}','{{order_id}}','{{items_table}}','{{total}}','{{address_name}}','{{address_street}}','{{address_pc}}','{{address_country}}'],
        'shipping-confirmation' => ['{{name}}','{{order_id}}','{{tracking_code}}','{{tracking_url}}','{{carrier_name}}','{{carrier_color}}','{{carrier_text_color}}','{{items_table}}','{{address_name}}','{{address_street}}','{{address_pc}}','{{address_country}}'],
      ];
    ?>
    <!-- Tabs -->
    <div class="tpl-tabs">
      <?php foreach ($tplNames as $key => $label): ?>
        <a class="tpl-tab <?= $activeTpl===$key?'active':'' ?>"
           href="?page=templates&tpl=<?= h($key) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Editor + Preview -->
    <div class="tpl-wrap">
      <!-- Links: code editor -->
      <div class="tpl-editor">
        <div class="tpl-panel-head">
          <span class="tpl-panel-title">HTML bewerken</span>
          <div style="display:flex;align-items:center;gap:8px">
            <button class="btn-save" id="saveBtn" onclick="startEdit()" style="background:var(--text)">Bewerken</button>
            <span class="save-msg" id="saveMsg"></span>
          </div>
        </div>
        <textarea class="tpl-textarea" id="tplCode" spellcheck="false" oninput="schedulePreview()" readonly
          style="background:#fafafa;color:var(--muted);cursor:default"><?= htmlspecialchars(
          file_get_contents(__DIR__ . '/../api/templates/' . $activeTpl . '.html'),
          ENT_QUOTES, 'UTF-8'
        ) ?></textarea>
      </div>

      <!-- Rechts: live preview -->
      <div class="tpl-preview">
        <div class="tpl-panel-head">
          <span class="tpl-panel-title">Voorbeeld (met testdata)</span>
          <a href="preview-email.php?t=<?= h($activeTpl) ?>" target="_blank"
             style="font-size:12px;color:var(--red);font-weight:700">Openen in nieuw tabblad ↗</a>
        </div>
        <iframe class="tpl-iframe" id="previewFrame"
                src="preview-email.php?t=<?= h($activeTpl) ?>"></iframe>
      </div>
    </div>

    <!-- Bevestigingsdialoog -->
    <div id="confirmDlg" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center">
      <div style="background:#fff;border-radius:14px;padding:36px 40px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center">
        <div style="font-size:36px;margin-bottom:12px">⚠️</div>
        <h2 style="font-family:var(--font-cond);font-size:22px;font-weight:900;text-transform:uppercase;color:var(--text);margin:0 0 10px">Weet je het zeker?</h2>
        <p style="font-size:14px;color:var(--muted);margin:0 0 28px;line-height:1.6">Je staat op het punt het e-mailsjabloon op te slaan. Klanten ontvangen voortaan de nieuwe versie van deze mail.</p>
        <div style="display:flex;gap:12px;justify-content:center">
          <button onclick="closeDlg(false)" style="padding:11px 24px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:14px;font-weight:600;cursor:pointer;color:var(--muted)">Annuleren</button>
          <button onclick="closeDlg(true)"  style="padding:11px 24px;border:none;border-radius:8px;background:var(--red);color:#fff;font-size:14px;font-weight:700;cursor:pointer">Ja, opslaan</button>
        </div>
      </div>
    </div>

    <!-- Beschikbare variabelen -->
    <div class="tpl-vars">
      <h4>Beschikbare variabelen &mdash; klik om te kopiëren</h4>
      <div class="tpl-var-grid">
        <?php foreach ($tplVars[$activeTpl] as $v): ?>
          <span class="tpl-var" onclick="copyVar(this, <?= json_encode($v) ?>)"><?= h($v) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <script>
    var previewTimer;
    var editing = false;
    var originalContent = '';

    function schedulePreview() {
      if (!editing) return;
      clearTimeout(previewTimer);
      previewTimer = setTimeout(refreshPreview, 1200);
    }
    function refreshPreview() {
      var frame = document.getElementById('previewFrame');
      frame.srcdoc = document.getElementById('tplCode').value;
    }
    function startEdit() {
      var btn  = document.getElementById('saveBtn');
      var ta   = document.getElementById('tplCode');
      originalContent     = ta.value;
      editing             = true;
      ta.removeAttribute('readonly');
      ta.style.background = '#fff';
      ta.style.color      = '';
      ta.style.cursor     = '';
      ta.focus();
      btn.textContent      = 'Opslaan';
      btn.style.background = '';
      btn.onclick          = confirmSave;
    }
    function setReadOnly() {
      var btn = document.getElementById('saveBtn');
      var ta  = document.getElementById('tplCode');
      ta.setAttribute('readonly', '');
      ta.style.background  = '#fafafa';
      ta.style.color       = 'var(--muted)';
      ta.style.cursor      = 'default';
      btn.textContent      = 'Bewerken';
      btn.style.background = 'var(--text)';
      btn.onclick          = startEdit;
      editing              = false;
    }
    function confirmSave() {
      document.getElementById('confirmDlg').style.display = 'flex';
    }
    function closeDlg(doSave) {
      document.getElementById('confirmDlg').style.display = 'none';
      if (doSave) {
        saveTemplate();
      } else {
        document.getElementById('tplCode').value = originalContent;
        document.getElementById('previewFrame').src = 'preview-email.php?t=<?= h($activeTpl) ?>&v=' + Date.now();
        setReadOnly();
      }
    }
    function saveTemplate() {
      var btn = document.getElementById('saveBtn');
      var msg = document.getElementById('saveMsg');
      btn.disabled = true;
      btn.textContent = 'Bezig…';
      var fd = new FormData();
      fd.append('template', <?= json_encode($activeTpl) ?>);
      fd.append('html', document.getElementById('tplCode').value);
      fetch('save-template.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (data.ok) {
            msg.style.color = '#16a34a';
            msg.textContent = '✅ Opgeslagen';
            document.getElementById('previewFrame').src = 'preview-email.php?t=<?= h($activeTpl) ?>&v=' + Date.now();
            setReadOnly();
          } else {
            msg.style.color = '#dc2626';
            msg.textContent = '❌ ' + (data.error || 'Fout');
          }
          btn.disabled = false;
          if (!data.ok) btn.textContent = 'Opslaan';
          setTimeout(function(){ msg.textContent = ''; }, 4000);
        });
    }
    function copyVar(el, text) {
      navigator.clipboard.writeText(text).then(function() {
        var orig = el.textContent;
        el.textContent = '✓ Gekopieerd';
        setTimeout(function(){ el.textContent = orig; }, 1500);
      });
    }
    </script>

    <?php else: ?>
    <!-- Ntfy setup kaart (verbergen zodra ingesteld) -->
    <?php if (strpos(NTFY_TOPIC, 'KIES') !== false): ?>
    <div class="setup-card" id="setupCard">
      <div class="setup-card__icon">📲</div>
      <div class="setup-card__body">
        <div class="setup-card__title">Telefoonmeldingen instellen</div>
        <div class="setup-card__text">
          Volg deze eenmalige stappen om een melding te ontvangen bij elke nieuwe bestelling:
          <ol class="setup-card__steps">
            <li>Open <strong>api/config.php</strong> en vervang <code>KIES-EEN-GEHEIM-WOORD</code> door iets unieks, bijv. <code>spanvloeren-jansen2024</code></li>
            <li>Ga op uw telefoon naar <strong>ntfy.sh/uw-gekozen-naam</strong> en klik op <em>Subscribe</em></li>
            <li>Of download de gratis <strong>ntfy-app</strong> (Android/iOS) en abonneer op hetzelfde onderwerp</li>
          </ol>
          U ontvangt direct een melding zodra een klant betaalt.
        </div>
      </div>
      <button class="setup-card__close" title="Sluiten" onclick="this.closest('.setup-card').remove()">&#10005;</button>
    </div>
    <?php else: ?>
    <div class="setup-card" id="setupCard" style="background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.3)">
      <div class="setup-card__icon">✅</div>
      <div class="setup-card__body">
        <div class="setup-card__title" style="color:var(--green)">Meldingen actief</div>
        <div class="setup-card__text">
          U ontvangt pushberichten op: <span class="setup-card__topic">ntfy.sh/<?= h(NTFY_TOPIC) ?></span><br>
          Nog niet geabonneerd? Ga naar die link op uw telefoon en klik op <em>Subscribe</em>.
        </div>
      </div>
      <button class="setup-card__close" title="Sluiten" onclick="this.closest('.setup-card').remove()">&#10005;</button>
    </div>
    <?php endif; ?>

    <!-- MyParcel koppeling -->
    <?php if ($mpStatus !== null): ?>
    <div class="mp-card">
      <div class="mp-card__icon"><?= $mpStatus['ok'] ? '📦' : '🔌' ?></div>
      <div class="mp-card__body">
        <div class="mp-card__title">MyParcel &mdash; Verzendlabels</div>
        <div class="mp-card__sub">
          <?php if ($mpStatus['ok']): ?>
            <?php if ($mpStatus['name']): ?><strong><?= h($mpStatus['name']) ?></strong><?php endif; ?>
            <?php if ($mpStatus['shop']): ?> &mdash; <?= h($mpStatus['shop']) ?><?php endif; ?>
          <?php else: ?>
            <?= h($mpStatus['error'] ?: 'Niet verbonden — controleer de API-sleutel in config.php') ?>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <?php if ($mpStatus['ok']): ?>
          <span class="mp-card__badge mp-card__badge--green">&#10003; Verbonden</span>
          <?php if (!$mpStatus['billing_ok']): ?>
            <span class="mp-card__badge mp-card__badge--orange">&#9888; Voeg betaalmethode toe in MyParcel</span>
          <?php endif; ?>
        <?php else: ?>
          <span class="mp-card__badge mp-card__badge--red">&#10007; Niet verbonden</span>
        <?php endif; ?>
      </div>
      <div class="mp-card__actions">
        <?php if (!$mpStatus['billing_ok'] && $mpStatus['ok']): ?>
          <a class="mp-card__btn mp-card__btn--primary" href="https://app.myparcel.nl/settings/general" target="_blank" rel="noopener">Betaling instellen ↗</a>
        <?php endif; ?>
        <a class="mp-card__btn mp-card__btn--ghost" href="https://app.myparcel.nl" target="_blank" rel="noopener">MyParcel ↗</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Statistieken -->
    <div class="stats">
      <div class="stat-card">
        <div class="stat-card__label">Totaal orders</div>
        <div class="stat-card__value"><?= (int)($stats['total'] ?? 0) ?></div>
      </div>
      <div class="stat-card stat-card--green">
        <div class="stat-card__label">Betaald</div>
        <div class="stat-card__value"><?= (int)($stats['paid'] ?? 0) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">Open / Wacht</div>
        <div class="stat-card__value"><?= (int)($stats['open_count'] ?? 0) ?></div>
      </div>
      <div class="stat-card stat-card--red">
        <div class="stat-card__label">Omzet totaal</div>
        <div class="stat-card__value">&euro;&thinsp;<?= number_format((float)($stats['revenue'] ?? 0), 0, ',', '.') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">Omzet vandaag</div>
        <div class="stat-card__value">&euro;&thinsp;<?= number_format((float)($stats['today'] ?? 0), 0, ',', '.') ?></div>
      </div>
    </div>

    <!-- Filter & zoeken -->
    <form class="toolbar" method="GET" action="">
      <div class="search-wrap">
        <input type="search" name="q" placeholder="Zoek op naam, order-ID, e-mail…" value="<?= h($search) ?>">
      </div>
      <?php if ($filter): ?><input type="hidden" name="status" value="<?= h($filter) ?>"><?php endif; ?>
      <div class="filter-tabs">
        <?php
        $tabs = [
          ''         => 'Alle',
          'paid'     => 'Betaald',
          'pending'  => 'In behandeling',
          'failed'   => 'Mislukt',
          'canceled' => 'Geannuleerd',
          'expired'  => 'Verlopen',
        ];
        foreach ($tabs as $val => $label):
          $active = $filter === $val ? ' active' : '';
          $href   = '?' . http_build_query(array_filter(['status' => $val, 'q' => $search]));
        ?>
          <a class="filter-tab<?= $active ?>" href="<?= h($href) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <!-- Tabel -->
    <div class="table-wrap">
      <div class="table-count">
        <?= count($orders) ?> bestelling<?= count($orders) !== 1 ? 'en' : '' ?> gevonden
      </div>
      <?php if ($orders): ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Datum</th>
              <th>Order-ID</th>
              <th>Klant</th>
              <th>Stad</th>
              <th>Bedrag</th>
              <th>Status</th>
              <th>Label</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr class="order-row" onclick="location.href='?order=<?= urlencode($o['order_id']) ?>'">
              <td style="color:var(--muted);font-size:13px;white-space:nowrap"><?= fmtDate($o['created_at']) ?></td>
              <td class="order-id"><?= h($o['order_id']) ?></td>
              <td>
                <div class="name-cell"><?= h($o['name'] ?: '—') ?></div>
                <div class="email-cell"><?= h($o['email'] ?: '') ?></div>
              </td>
              <td style="color:var(--muted)"><?= h($o['city'] ?: '—') ?></td>
              <td class="amount-cell"><?= fmtAmount($o['amount']) ?></td>
              <td><?= statusBadge($o['status']) ?></td>
              <td onclick="event.stopPropagation()" style="white-space:nowrap">
                <?php if (!empty($o['myparcel_shipment_id'])): ?>
                  <button class="label-btn label-btn--print"
                          onclick="printLabel(<?= json_encode($o['myparcel_shipment_id']) ?>,<?= json_encode($o['order_id']) ?>)">
                    🖨 Print
                  </button>
                <?php elseif ($o['status'] === 'paid'): ?>
                  <button class="label-btn label-btn--create"
                          onclick="createAndPrint(<?= json_encode($o['order_id']) ?>,this)">
                    📦 Aanmaken
                  </button>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="empty-state">
          <div style="font-size:40px;margin-bottom:12px">📦</div>
          <div style="font-size:16px;font-weight:600">Geen bestellingen gevonden</div>
          <div style="font-size:14px;margin-top:6px">Bestellingen verschijnen hier zodra klanten afrekenen via Mollie.</div>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /main -->
</div><!-- /layout -->
<?php endif; // templates vs orders
endif; // loggedIn ?>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/admin/sw.js');
}

// ── Label printen (shipment_id al bekend) ──────────────────────────────────────
function printLabel(shipmentId, orderId) {
  window.open('/admin/download-label.php?id=' + encodeURIComponent(shipmentId)
    + '&order=' + encodeURIComponent(orderId) + '&print=1', '_blank');
}

// ── Label aanmaken + printen (nog geen shipment_id) ────────────────────────────
function createAndPrint(orderId, btn) {
  btn.disabled    = true;
  btn.textContent = '⏳…';

  var fd = new FormData();
  fd.append('order_id', orderId);
  fd.append('carrier', '<?= defined('AUTO_CARRIER') ? AUTO_CARRIER : 5 ?>');
  fd.append('package_type', '<?= defined('AUTO_PACKAGE_TYPE') ? AUTO_PACKAGE_TYPE : 1 ?>');

  fetch('/admin/create-label.php', { method: 'POST', body: fd })
    .then(function(r) {
      var ct = r.headers.get('Content-Type') || '';
      if (ct.indexOf('pdf') !== -1) {
        return r.blob().then(function(blob) {
          var url = URL.createObjectURL(blob);
          window.open(url, '_blank');
          btn.textContent = '🖨 Print';
          btn.classList.replace('label-btn--create', 'label-btn--print');
          btn.disabled    = false;
          btn.onclick     = function() { window.open(url, '_blank'); };
        });
      }
      return r.json().then(function(data) {
        if (data.pending) {
          btn.textContent = '🖨 Print';
          btn.classList.replace('label-btn--create', 'label-btn--print');
          btn.disabled    = false;
          btn.onclick     = function() {
            window.open('/admin/download-label.php?id=' + encodeURIComponent(data.shipment_id)
              + '&order=' + encodeURIComponent(orderId) + '&print=1', '_blank');
          };
        } else {
          alert('Fout bij aanmaken label: ' + (data.error || 'Onbekend'));
          btn.textContent = '📦 Aanmaken';
          btn.disabled    = false;
        }
      });
    })
    .catch(function() {
      alert('Verbindingsfout, probeer opnieuw');
      btn.textContent = '📦 Aanmaken';
      btn.disabled    = false;
    });
}
</script>
</body>
</html>
