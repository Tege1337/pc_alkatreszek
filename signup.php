<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_lib.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($token)) {
        $errors[] = 'Érvénytelen kérés. Próbáld újra.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Adj meg egy nevet (max. 100 karakter).';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adj meg egy érvényes e-mail címet.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'A jelszó legalább 8 karakter legyen.';
        }
        if ($password !== $password2) {
            $errors[] = 'A két jelszó nem egyezik.';
        }

        if (empty($errors)) {
            try {
                $pdo = get_pdo();
                ensure_database_schema($pdo);

                $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $existsStmt->execute([$email]);
                if ($existsStmt->fetch()) {
                    $errors[] = 'Ezzel az e-mail címmel már létezik fiók.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
                    $insert->execute([$name, $email, $hash]);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                    $_SESSION['user_name'] = $name;
                    header('Location: cart.html');
                    exit;
                }
            } catch (Throwable $e) {
                $errors[] = 'Szerverhiba történt. Ellenőrizd az adatbázis beállításokat.';
            }
        }
    }
}
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
    <title>Regisztráció - Elektromos Roller Bolt</title>
</head>
<body>
    <nav>
        <a href="index.html">FŐOLDAL</a>
        <a href="robogok.html">ROBOGÓK</a>
        <a href="motorok.html">MOTOR</a>
        <a href="aksik.html">AKKUMULÁTOR</a>
        <a href="kerekek.html">KEREKEK</a>
        <a href="tortenelem.html">TÖRTÉNET</a>
        <a href="contact.php">KAPCSOLAT</a>
        <a href="cart.html">🛒 <span id="cart-count"></span></a>
    </nav>
    <main>
        <h1>Regisztráció</h1>
        <div class="info-box checkout-form-box">
            <?php if (!empty($errors)): ?>
                <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="POST" class="php-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="name">Teljes név</label>
                    <input type="text" id="name" name="name" maxlength="100" required value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="email">E-mail cím</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Jelszó (min. 8 karakter)</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Jelszó újra</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <button type="submit" class="btn-checkout">Regisztráció</button>
            </form>
            <p style="margin-top:12px;">Már van fiókod? <a href="login.php" style="color:#4CAF50;">Belépés</a></p>
        </div>
    </main>
    <footer>
        <p>&copy; 2026 Elektromos Roller Bolt. Minden jog fenntartva.</p>
    </footer>
    <div id="toast"></div>
    <script src="cart.js"></script>
</body>
</html>
