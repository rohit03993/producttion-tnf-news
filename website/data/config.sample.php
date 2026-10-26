<?php

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit;
}

/*
 * Copy these lines into data/config.php.
 *
 * Password hash (run this yourself, then paste the result):
 *   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * WhatsApp number, digits only, with country code:
 *   919876543210
 *
 * Do not store the plain password. Leave whatsapp as an empty string
 * if you do not want the WhatsApp button yet.
 */
return [
    'password_hash' => '',
    'whatsapp' => '',
    'timezone' => 'Asia/Kolkata',
];
