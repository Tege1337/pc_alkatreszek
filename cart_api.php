<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    ensure_database_schema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Adatbázis kapcsolat hiba.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = require_logged_in_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT product_name, price, quantity FROM cart_items WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $cart = array_map(static fn(array $r): array => [
        'name' => (string)$r['product_name'],
        'price' => (float)$r['price'],
        'qty' => (int)$r['quantity'],
    ], $rows);

    echo json_encode(['ok' => true, 'cart' => $cart], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($body)) {
        $body = [];
    }

    $cart = sanitize_cart(isset($body['cart']) && is_array($body['cart']) ? $body['cart'] : []);

    try {
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $delete->execute([$userId]);

        if (!empty($cart)) {
            $insert = $pdo->prepare('INSERT INTO cart_items (user_id, product_name, price, quantity) VALUES (?, ?, ?, ?)');
            foreach ($cart as $item) {
                $insert->execute([$userId, $item['name'], $item['price'], $item['qty']]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'A kosár mentése sikertelen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Nem támogatott metódus.'], JSON_UNESCAPED_UNICODE);
