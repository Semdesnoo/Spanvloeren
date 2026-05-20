<?php
// ══════════════════════════════════════════════════════════════════════════════
//  E-mail hulpfuncties – sjablonen laden, renderen en versturen
// ══════════════════════════════════════════════════════════════════════════════

define('TEMPLATES_DIR', __DIR__ . '/templates/');

function mailLoadTemplate(string $name): string {
    $path = TEMPLATES_DIR . basename($name) . '.html';
    if (!file_exists($path)) {
        error_log("[Spanvloeren Mail] Sjabloon niet gevonden: $path");
        return '';
    }
    return file_get_contents($path);
}

function mailRender(string $template, array $vars): string {
    foreach ($vars as $key => $value) {
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    return $template;
}

function mailBuildItemRows(array $items): string {
    $rows = '';
    foreach ($items as $item) {
        $name  = htmlspecialchars($item['name']      ?? '', ENT_QUOTES, 'UTF-8');
        $qty   = (int)($item['qty']                  ?? 1);
        $price = '&euro;&nbsp;' . number_format((float)($item['linePrice'] ?? 0), 2, ',', '.');
        $type  = ($item['bundle'] ?? false) ? '6-Pack' : '1L';
        $rows .= "<tr>
          <td style='padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;color:#111827'>$name <span style='color:#9ca3af;font-size:13px'>($type)</span></td>
          <td style='padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;color:#6b7280;text-align:center'>$qty</td>
          <td style='padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:700;color:#111827;text-align:right'>$price</td>
        </tr>";
    }
    return $rows;
}

function mailSend(string $to, string $subject, string $html): bool {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Spanvloeren.nl <info@spanvloeren.nl>',
        'Reply-To: info@spanvloeren.nl',
    ]);
    return mail($to, $subject, $html, $headers);
}

// ── Orderbevestiging naar klant ───────────────────────────────────────────────
function mailSendOrderConfirmation(string $orderId, array $customer, array $items, string $amount): bool {
    $email = $customer['email'] ?? '';
    if (!$email) return false;

    $html = mailRender(mailLoadTemplate('order-confirmation'), [
        'name'            => htmlspecialchars($customer['name']      ?? 'klant',   ENT_QUOTES, 'UTF-8'),
        'order_id'        => htmlspecialchars($orderId,                             ENT_QUOTES, 'UTF-8'),
        'items_table'     => mailBuildItemRows($items),
        'total'           => '&euro;&nbsp;' . number_format((float)$amount, 2, ',', '.'),
        'address_name'    => htmlspecialchars($customer['name']      ?? '',         ENT_QUOTES, 'UTF-8'),
        'address_street'  => htmlspecialchars(($customer['straat'] ?? '') . ' ' . ($customer['huisnummer'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'address_pc'      => htmlspecialchars(($customer['postcode'] ?? '') . ' ' . ($customer['stad'] ?? ''),     ENT_QUOTES, 'UTF-8'),
        'address_country' => htmlspecialchars($customer['land']      ?? '',         ENT_QUOTES, 'UTF-8'),
    ]);

    return mailSend($email, 'Bevestiging van uw bestelling ' . $orderId, $html);
}

// ── Verzendbevestiging met track & trace ─────────────────────────────────────
function mailSendShippingConfirmation(array $order, string $tracking, string $carrier): bool {
    $email = $order['email'] ?? '';
    if (!$email) return false;

    if ($carrier === 'dhl') {
        $trackUrl    = 'https://www.dhl.com/nl-nl/home/volg-uw-zending.html?tracking-id=' . urlencode($tracking);
        $carrierName = 'DHL';
        $carrierClr  = '#ffcc00';
        $carrierTxt  = '#000000';
    } else {
        $trackUrl    = 'https://postnl.nl/tracktrace/?B=' . urlencode($tracking) . '&P=' . urlencode($order['postcode'] ?? '') . '&T=C&L=NL';
        $carrierName = 'PostNL';
        $carrierClr  = '#ff6200';
        $carrierTxt  = '#ffffff';
    }

    $items = json_decode($order['items'] ?: '[]', true) ?: [];

    $html = mailRender(mailLoadTemplate('shipping-confirmation'), [
        'name'              => htmlspecialchars($order['name']    ?? 'klant', ENT_QUOTES, 'UTF-8'),
        'order_id'          => htmlspecialchars($order['order_id'],            ENT_QUOTES, 'UTF-8'),
        'tracking_code'     => htmlspecialchars($tracking,                     ENT_QUOTES, 'UTF-8'),
        'tracking_url'      => htmlspecialchars($trackUrl,                     ENT_QUOTES, 'UTF-8'),
        'carrier_name'      => $carrierName,
        'carrier_color'     => $carrierClr,
        'carrier_text_color'=> $carrierTxt,
        'items_table'       => mailBuildItemRows($items),
        'address_name'      => htmlspecialchars($order['name']    ?? '',        ENT_QUOTES, 'UTF-8'),
        'address_street'    => htmlspecialchars(trim(($order['street'] ?? '') . ' ' . ($order['number'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'address_pc'        => htmlspecialchars(trim(($order['postcode'] ?? '') . ' ' . ($order['city'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'address_country'   => htmlspecialchars($order['country'] ?? '',        ENT_QUOTES, 'UTF-8'),
    ]);

    return mailSend($email, 'Uw bestelling ' . $order['order_id'] . ' is verzonden!', $html);
}
