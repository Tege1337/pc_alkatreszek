<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Érvénytelen kérés. Kérjük, próbálja újra.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate inputs
        if ($name === '') {
            $errors[] = 'A név megadása kötelező.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'A név túl hosszú (max. 100 karakter).';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Érvényes e-mail cím szükséges.';
        }

        if ($phone !== '' && !preg_match('/^[\d\s+\-()\/.]{6,30}$/', $phone)) {
            $errors[] = 'Érvénytelen telefonszám formátum.';
        }

        if ($message === '') {
            $errors[] = 'Az üzenet megadása kötelező.';
        } elseif (mb_strlen($message) > 2000) {
            $errors[] = 'Az üzenet túl hosszú (max. 2000 karakter).';
        }

        if (empty($errors)) {
            $inquiries_dir = __DIR__ . '/inquiries';
            if (!is_dir($inquiries_dir)) {
                mkdir($inquiries_dir, 0750, true);
                file_put_contents($inquiries_dir . '/.htaccess', "Require all denied\n");
            }

            $inquiry_id = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $inquiry = [
                'id'      => $inquiry_id,
                'date'    => date('Y-m-d H:i:s'),
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'message' => $message,
            ];

            file_put_contents(
                $inquiries_dir . '/' . $inquiry_id . '.json',
                json_encode($inquiry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <title>Kapcsolat - Elektromos Roller Bolt</title>
</head>

<body>
    <nav>
        <a href="index.html">FŐOLDAL</a>
        <a href="robogok.html">ROBOGÓK</a>
        <a href="motorok.html">MOTOR</a>
        <a href="aksik.html">AKKUMULÁTOR</a>
        <a href="kerekek.html">KEREKEK</a>
        <a href="tortenelem.html">TÖRTÉNET</a>
        <a href="contact.php" class="active">KAPCSOLAT</a>
        <a href="cart.html">🛒 <span id="cart-count"></span></a>
    </nav>
    <main>
        <h1>Kapcsolat</h1>

        <div class="info-box checkout-form-box">
            <?php if ($success): ?>
            <div class="form-success">
                <h2>✅ Üzenetét megkaptuk!</h2>
                <p>Köszönjük megkeresését! Hamarosan felvesszük Önnel a kapcsolatot.</p>
                <a href="index.html" class="btn-checkout" style="display:inline-block;text-decoration:none;margin-top:10px;">Vissza a főoldalra</a>
            </div>
            <?php else: ?>

            <div class="info-content">
                <h2>Írjon nekünk!</h2>
                <p>Kérdése van egy termékkel kapcsolatban, vagy segítségre van szüksége? Töltse ki az alábbi
                    űrlapot és hamarosan felvesszük Önnel a kapcsolatot.</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="contact.php" class="php-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

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
                    <label for="phone">Telefonszám <span class="optional">(opcionális)</span></label>
                    <input type="tel" id="phone" name="phone" maxlength="30"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="pl. +36 30 123 4567">
                </div>

                <div class="form-group">
                    <label for="message">Üzenet <span class="required">*</span></label>
                    <textarea id="message" name="message" required maxlength="2000" rows="6"
                        placeholder="Írja ide kérdését..."><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn-checkout">📨 Üzenet küldése</button>
            </form>
            <?php endif; ?>
        </div>
    </main>
    <footer>
        <p>&copy; 2026 Elektromos Roller Bolt. Minden jog fenntartva.</p>
    </footer>
    <div id="toast"></div>
    <script src="cart.js"></script>
</body>

</html>
