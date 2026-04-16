<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return (string)$_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool
{
    return hash_equals((string)$_SESSION['csrf_token'], $token);
}

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    if (is_int($id) && $id > 0) {
        return $id;
    }

    if (is_string($id) && ctype_digit($id) && (int)$id > 0) {
        return (int)$id;
    }

    return null;
}

function current_user_name(): ?string
{
    $name = $_SESSION['user_name'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

function require_logged_in_user_id(): int
{
    $userId = current_user_id();
    if ($userId === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Nincs bejelentkezve.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $userId;
}

function sanitize_cart(array $items): array
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
        $price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : -1;
        $qty = isset($item['qty']) && is_numeric($item['qty']) ? (int)$item['qty'] : 0;

        if ($name === '' || mb_strlen($name) > 200 || $price < 0 || $qty < 1 || $qty > 1000) {
            continue;
        }

        $clean[] = [
            'name' => $name,
            'price' => round($price, 2),
            'qty' => $qty,
        ];
    }

    return $clean;
}
