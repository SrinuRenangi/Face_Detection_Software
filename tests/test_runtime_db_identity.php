<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = Config\Database::connect('default');
$row = $db->query('SELECT CURRENT_USER() AS `cur_user`, USER() AS `ses_user`')->getRowArray();

echo "=== CODEIGNITER RUNTIME DATABASE IDENTITY ===" . PHP_EOL;
echo "Connected Database: " . $db->getDatabase() . PHP_EOL;
echo "CURRENT_USER():     " . $row['cur_user'] . PHP_EOL;
echo "USER():             " . $row['ses_user'] . PHP_EOL;
echo "=============================================" . PHP_EOL;
