<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;
$order_id = null;
$cart = [];

// Decode cart from GET param (URL-encoded JSON)
$cart_raw = $_GET['cart'] ?? '';
if ($cart_raw !== '') {
    $decoded = json_decode($cart_raw, true);
    if (is_array($decoded)) {
        $cart = $decoded;
    }
}

function sanitize_cart(array $items): array {
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name  = isset($item['name'])  && is_string($item['name'])  ? $item['name']          : null;
        $price = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price']  : null;
        $qty   = isset($item['qty'])   && is_numeric($item['qty'])   ? (int)$item['qty']      : null;
        if ($name === null || $price === null || $qty === null) continue;
        if ($price < 0 || $qty < 1 || mb_strlen($name) > 200) continue;
        $clean[] = ['name' => $name, 'price' => $price, 'qty' => $qty];
    }
    return $clean;
}

$cart = sanitize_cart($cart);

function format_price(float $price): string {
    return number_format($price, 0, ',', "\u{00A0}") . ' Ft';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Érvénytelen kérés. Kérjük, próbálja újra.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $address = trim($_POST['address'] ?? '');

        // Decode cart from POST
        $cart_post = json_decode($_POST['cart'] ?? '[]', true);
        if (!is_array($cart_post)) {
            $cart_post = [];
        }
        $cart_post = sanitize_cart($cart_post);

        // Validate inputs
        if ($name === '') {
            $errors[] = 'A név megadása kötelező.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'A név túl hosszú (max. 100 karakter).';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Érvényes e-mail cím szükséges.';
        }

        if ($phone === '') {
            $errors[] = 'A telefonszám megadása kötelező.';
        } elseif (!preg_match('/^[\d\s+\-()\/.]{6,30}$/', $phone)) {
            $errors[] = 'Érvénytelen telefonszám formátum.';
        }

        if ($address === '') {
            $errors[] = 'A szállítási cím megadása kötelező.';
        } elseif (mb_strlen($address) > 250) {
            $errors[] = 'A cím túl hosszú (max. 250 karakter).';
        }

        if (empty($cart_post)) {
            $errors[] = 'A kosár üres. Kérjük, adjon terméket a kosárhoz.';
        }

        if (empty($errors)) {
            $orders_dir = __DIR__ . '/orders';
            if (!is_dir($orders_dir)) {
                mkdir($orders_dir, 0750, true);
                // Deny direct web access to orders
                file_put_contents($orders_dir . '/.htaccess', "Require all denied\n");
            }

            $order_id = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $total = (float)array_sum(array_map(
                fn($i) => (float)$i['price'] * (int)$i['qty'],
                $cart_post
            ));

            $order = [
                'id'      => $order_id,
                'date'    => date('Y-m-d H:i:s'),
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'address' => $address,
                'cart'    => $cart_post,
                'total'   => $total,
            ];

            file_put_contents(
                $orders_dir . '/' . $order_id . '.json',
                json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // Regenerate CSRF token after successful submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $cart = $cart_post;
            $success = true;
        }
    }
}

$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <title>Megrendelés - Elektromos Roller Bolt</title>
</head>

<body>
    <nav>
        <a href="index.html">FŐOLDAL</a>
        <a href="robogok.html">ROBOGÓK</a>
        <a href="motorok.html">MOTOR</a>
        <a href="aksik.html">AKKUMULÁTOR</a>
        <a href="kerekek.html">KEREKEK</a>
        <a href="tortenelem.html">TÖRTÉNET</a>
        <a href="contact.html">KAPCSOLAT</a>
        <a href="cart.html">🛒 <span id="cart-count"></span></a>
    </nav>
    <main>
        <h1>Megrendelés</h1>

        <?php if ($success): ?>
        <div class="info-box checkout-success">
            <h2>✅ Köszönjük a rendelést!</h2>
            <p>A rendelési azonosítód: <strong><?= htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p>Hamarosan felvesszük Önnel a kapcsolatot a megadott e-mail címen.</p>
            <h3>Rendelt termékek:</h3>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Termék</th><th>Mennyiség</th><th>Egységár</th><th>Összesen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$item['qty'] ?> db</td>
                        <td><?= format_price((float)$item['price']) ?></td>
                        <td><?= format_price((float)$item['price'] * (int)$item['qty']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="cart-total">
                Végösszeg: <?= format_price((float)array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['qty'], $cart))) ?>
            </p>
            <a href="index.html" class="btn-checkout" style="display:inline-block;text-decoration:none;margin-top:10px;">Vissza a főoldalra</a>
        </div>
        <script>
            // Clear the cart from localStorage after successful order
            localStorage.removeItem('cart');
            if (typeof syncCartWithServer === 'function') {
                syncCartWithServer();
            }
        </script>

        <?php else: ?>

        <?php if (!empty($errors)): ?>
        <div class="info-box form-errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($cart)): ?>
        <div class="info-box" style="text-align:center;padding:40px;">
            <p style="font-size:22px;">A kosár üres. <a href="index.html" style="color:#4CAF50;">Válogasson termékeink közül!</a></p>
        </div>
        <?php else: ?>

        <div class="info-box checkout-form-box">
            <h2>Kosár összesítő</h2>
            <table class="cart-table">
                <thead>
                    <tr><th>Termék</th><th>Mennyiség</th><th>Egységár</th><th>Összesen</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$item['qty'] ?> db</td>
                        <td><?= format_price((float)$item['price']) ?></td>
                        <td><?= format_price((float)$item['price'] * (int)$item['qty']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="cart-total">
                Végösszeg: <?= format_price((float)array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['qty'], $cart))) ?>
            </p>

            <h2>Szállítási adatok</h2>
            <form method="POST" action="checkout.php" class="php-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="cart" value="<?= htmlspecialchars(json_encode($cart), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="name">Teljes név <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="100"
                        value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="pl. Kovács János">
                </div>

                <div class="form-group">
                    <label for="email">E-mail cím <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="pl. kovacs.janos@email.hu">
                </div>

                <div class="form-group">
                    <label for="phone">Telefonszám <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required maxlength="30"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="pl. +36 30 123 4567">
                </div>

                <div class="form-group">
                    <label for="address">Szállítási cím <span class="required">*</span></label>
                    <input type="text" id="address" name="address" required maxlength="250"
                        value="<?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="pl. 1234 Budapest, Példa utca 1.">
                </div>

                <button type="submit" class="btn-checkout">✅ Megrendelés véglegesítése</button>
            </form>
        </div>

        <?php endif; ?>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; 2026 Elektromos Roller Bolt. Minden jog fenntartva.</p>
    </footer>
    <div id="toast"></div>
    <script src="cart.js"></script>
</body>

</html>
