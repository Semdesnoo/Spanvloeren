<?php
// Rendert een e-mailsjabloon met voorbeelddata voor preview in de admin.
session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/mail.php';

if (!($_SESSION['sv_admin'] ?? false)) {
    http_response_code(403); exit('Niet ingelogd');
}

$allowed = ['order-confirmation', 'shipping-confirmation'];
$tpl     = in_array($_GET['t'] ?? '', $allowed) ? $_GET['t'] : 'order-confirmation';

// Voorbeelddata
$sampleItems = [
    ['name' => 'Spanvloer Atlas 6x6m', 'qty' => 1, 'linePrice' => 1249.00, 'bundle' => false],
    ['name' => 'Tatami Puzzelmatten',   'qty' => 4, 'linePrice' => 199.80,  'bundle' => false],
];
$sampleOrder = [
    'name'       => 'Jan Jansen',
    'order_id'   => 'ORD-20260519-DEMO',
    'street'     => 'Hoofdstraat',
    'number'     => '42',
    'postcode'   => '1234 AB',
    'city'       => 'Amsterdam',
    'country'    => 'Nederland',
    'email'      => 'jan@voorbeeld.nl',
    'items'      => json_encode($sampleItems),
];

if ($tpl === 'order-confirmation') {
    $vars = [
        'name'            => 'Jan Jansen',
        'order_id'        => 'ORD-20260519-DEMO',
        'items_table'     => mailBuildItemRows($sampleItems),
        'total'           => '&euro;&nbsp;1.448,80',
        'address_name'    => 'Jan Jansen',
        'address_street'  => 'Hoofdstraat 42',
        'address_pc'      => '1234 AB Amsterdam',
        'address_country' => 'Nederland',
    ];
} else {
    $vars = [
        'name'               => 'Jan Jansen',
        'order_id'           => 'ORD-20260519-DEMO',
        'tracking_code'      => '3SDEVC987654321',
        'tracking_url'       => '#',
        'carrier_name'       => 'PostNL',
        'carrier_color'      => '#ff6200',
        'carrier_text_color' => '#ffffff',
        'items_table'        => mailBuildItemRows($sampleItems),
        'address_name'       => 'Jan Jansen',
        'address_street'     => 'Hoofdstraat 42',
        'address_pc'         => '1234 AB Amsterdam',
        'address_country'    => 'Nederland',
    ];
}

echo mailRender(mailLoadTemplate($tpl), $vars);
