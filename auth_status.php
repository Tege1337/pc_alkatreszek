<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_lib.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'loggedIn' => current_user_id() !== null,
    'userName' => current_user_name(),
], JSON_UNESCAPED_UNICODE);
