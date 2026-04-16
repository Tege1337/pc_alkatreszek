<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
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
                // Deny direct web access to inquiries
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

            // Regenerate CSRF token after successful submission
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
        <a href="index.html" class="nav-brand">⚡ ROLLER BOLT</a>
        <div class="nav-links">
            <a href="index.html">FŐOLDAL</a>
            <a href="robogok.html">ROBOGÓK</a>
            <a href="motorok.html">MOTOR</a>
            <a href="aksik.html">AKKUMULÁTOR</a>
            <a href="kerekek.html">KEREKEK</a>
            <a href="tortenelem.html">TÖRTÉNET</a>
            <a href="contact.php" class="active">KAPCSOLAT</a>
        </div>
        <div class="nav-icons">
            <div class="nav-profile-wrap">
                <button class="nav-icon-btn" aria-label="Profil">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                </button>
                <div class="profile-dropdown" id="auth-links">
                    <a href="login.php">Belépés</a>
                    <a href="signup.php">Regisztráció</a>
                </div>
            </div>
            <a href="cart.html" class="nav-icon-btn cart-icon-btn" aria-label="Kosár">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM7.2 14.2L5 4H2V2H4.3l.8 3.2L7.8 16H18l1.7-8H7.5l-.3-2h12.6l-2.2 10H7.2z"/></svg>
                <span id="cart-count"></span>
            </a>
        </div>
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
