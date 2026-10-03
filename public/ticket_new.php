<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();

$errors = [];
$form = ['title' => '', 'description' => '', 'priority' => 'medium'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['title']       = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['priority']    = $_POST['priority'] ?? 'medium';

    // Server-side validation (never rely on the browser alone)
    if ($form['title'] === '' || text_length($form['title']) > 150) {
        $errors[] = 'Title is required and must be 150 characters or fewer.';
    }
    if ($form['description'] === '') {
        $errors[] = 'Describe the problem so a technician can help.';
    }
    if (!in_array($form['priority'], ['low', 'medium', 'high'], true)) {
        $errors[] = 'Choose a valid priority.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO tickets (title, description, priority, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, \'open\', ?, ?, ?)'
        );
        $stmt->execute([$form['title'], $form['description'], $form['priority'], $user['id'], now(), now()]);
        $id = (int) db()->lastInsertId();

        audit('ticket_created', 'ticket', $id, 'Priority: ' . $form['priority']);
        flash('Ticket #' . $id . ' created.');
        redirect('ticket.php?id=' . $id);
    }
}

$title = 'New ticket';
require __DIR__ . '/../src/views/header.php';
?>
<section class="narrow">
    <h1>New ticket</h1>
    <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
    <form method="post" class="stack" data-validate>
        <?= csrf_field() ?>
        <label>Title <input name="title" maxlength="150" value="<?= e($form['title']) ?>" required></label>
        <label>Description <textarea name="description" rows="6" required><?= e($form['description']) ?></textarea></label>
        <label>Priority
            <select name="priority">
                <?php foreach (['low', 'medium', 'high'] as $p): ?>
                    <option value="<?= $p ?>" <?= $form['priority'] === $p ? 'selected' : '' ?>><?= label($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Create ticket</button>
    </form>
</section>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
