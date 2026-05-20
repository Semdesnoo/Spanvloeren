<?php
// Kopieer dit bestand naar config.php en vul uw eigen sleutels in.
// Commit NOOIT config.php naar git — het staat in .gitignore.

define('MOLLIE_API_KEY', 'test_VERVANG_MET_UW_SLEUTEL');
define('SITE_URL',       'https://www.spanvloeren.nl');
define('ORDER_EMAIL',    'info@spanvloeren.nl');
define('SHIPPING_COST',           4.95);
define('FREE_SHIPPING_THRESHOLD', 50.00);
define('ADMIN_PASSWORD', 'VERVANG_MET_STERK_WACHTWOORD');
define('DB_PATH',        __DIR__ . '/orders.db');
define('MYPARCEL_API_KEY', 'VERVANG_MET_UW_MYPARCEL_SLEUTEL');
define('AUTO_CARRIER',      5);
define('AUTO_PACKAGE_TYPE', 1);
define('NTFY_TOPIC', 'VERVANG_MET_GEHEIM_ONDERWERP');
