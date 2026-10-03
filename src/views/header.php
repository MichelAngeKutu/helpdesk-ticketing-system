<?php /** @var string $title */ $user = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><?= e($config['app_name']) ?></a>
    <?php if ($user): ?>
        <nav>
            <a href="index.php">Tickets</a>
            <a href="ticket_new.php">New ticket</a>
            <a href="access.php"><?= $user['role'] === 'admin' ? 'Access requests' : 'Request access' ?></a>
            <?php if ($user['role'] === 'admin'): ?><a href="audit.php">Audit log</a><?php endif; ?>
        </nav>
        <form class="signout" method="post" action="logout.php">
            <?= csrf_field() ?>
            <span><?= e($user['full_name']) ?> (<?= e(label($user['role'])) ?>)</span>
            <button type="submit" class="link-button">Sign out</button>
        </form>
    <?php endif; ?>
</header>
<main>
<?php if ($msg = flash()): ?><p class="flash"><?= e($msg) ?></p><?php endif; ?>
