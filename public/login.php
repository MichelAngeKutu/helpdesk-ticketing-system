<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);           // prevents session fixation
        $_SESSION['user_id'] = (int) $row['id'];
        audit('login_success', 'user', (int) $row['id']);
        redirect('index.php');
    }

    // Failed attempts are logged too, with the username that was tried
    audit('login_failed', null, null, 'Username tried: ' . $username, $row ? (int) $row['id'] : null);
    $error = 'Incorrect username or password.';
}

$title = 'Sign in';
require __DIR__ . '/../src/views/header.php';
?>
<section class="narrow">
    <h1>Sign in</h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>Username <input name="username" value="<?= e($username) ?>" required autofocus></label>
        <label>Password <input name="password" type="password" required></label>
        <button type="submit">Sign in</button>
    </form>
</section>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
