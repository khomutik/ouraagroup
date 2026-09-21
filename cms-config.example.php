<?php
/**
 * Copy this file to cms-config.php on the hosting account before opening setup.php.
 * Do not commit cms-config.php or send its value in a chat.
 */
return [
    // Create a new 48+ character random value for the live site.
    'setup_token' => 'PUT-A-LONG-RANDOM-SECRET-HERE',
    'timezone' => 'Europe/Moscow',
    'max_image_bytes' => 5 * 1024 * 1024,
    'max_pdf_bytes' => 15 * 1024 * 1024,
];
