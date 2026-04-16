<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_lib.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($token)) {
        $errors[] = 'Érvénytelen kérés. Próbáld újra.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adj meg egy érvényes e-mail címet.';
        }
        if ($password === '') {
            $errors[] = 'A jelszó kötelező.';
        }

        if (empty($errors)) {
            try {
                $pdo = get_pdo();
                ensure_database_schema($pdo);

                $stmt = $pdo->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                    $errors[] = 'Hibás e-mail cím vagy jelszó.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = (string)$user['full_name'];
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
    <title>Belépés - Elektromos Roller Bolt</title>
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
        <h1>Belépés</h1>
        <div class="info-box checkout-form-box">
            <?php if (!empty($errors)): ?>
                <div class="form-errors"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="POST" class="php-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="email">E-mail cím</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Jelszó</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-checkout">Belépés</button>
            </form>
            <p style="margin-top:12px;">Nincs még fiókod? <a href="signup.php" style="color:#4CAF50;">Regisztráció</a></p>
        </div>
    </main>
    <footer>
        <p>&copy; 2026 Elektromos Roller Bolt. Minden jog fenntartva.</p>
    </footer>
    <div id="toast"></div>
    <script src="cart.js"></script>
</body>
</html>
