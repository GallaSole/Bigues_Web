<?php

declare(strict_types=1);
require __DIR__ . '/inc/common.php';
$settings = br_read_php_array(BR_SETTINGS_FILE);
$users = is_array($settings['users'] ?? null) ? $settings['users'] : [];
$users['laia'] = password_hash('GalaiLaialesmillors', PASSWORD_DEFAULT);
$settings['users'] = $users;
br_write_php_array(BR_SETTINGS_FILE, $settings);
