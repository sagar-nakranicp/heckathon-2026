<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
echo "PHP version: " . PHP_VERSION . "\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo "curl loaded: " . (extension_loaded('curl') ? 'yes' : 'no') . "\n";
echo "\nIf PHP is 7.4 here but you have 8.2 on CLI, switch Apache to mod-php8.2 (see README).\n";
