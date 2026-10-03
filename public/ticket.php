<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT t.*, c.full_name AS created_by_name, a.full_name AS assigned_name
     FROM tickets t
     JOIN users c ON c.id = t.created_by
     LEFT JOIN users a ON a.id = t.assigned_to
     WHERE t.id = ?'
);
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

// Users may only open their own tickets unless they are staff or were granted access
$canView = is_staff($user)
    || (int) $ticket['created_by'] === (int) $user['id']
    || has_permission($user, 'tickets.view_all');
if (!$canView) {
    http_response_code(403);
    exit('You do not have access to this ticket.');
}

$statuses = ['open', 'in_progress', 'resolved', 'closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'comment') {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            db()->prepare('INSERT INTO ticket_comments (ticket_id, user_id, body, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$id, $user['id'], $body, now()]);
            db()->prepare('UPDATE tickets SET updated_at = ? WHERE id = ?')->execute([now(), $id]);
            audit('comment_added', 'ticket', $id);
            flash('Comment added.');
        }
    }

    // Only technicians and admins can change status or assignment
    if ($action === 'status' && is_staff($user)) {
        $new = $_POST['status'] ?? '';
        if (in_array($new, $statuses, true) && $new !== $ticket['status']) {
            db()->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?')->execute([$new, now(), $id]);
            audit('status_changed', 'ticket', $id, $ticket['status'] . ' -> ' . $new);
            flash('Status changed to ' . label($new) . '.');
        }
    }

    if ($action === 'assign' && is_staff($user)) {
        $assignee = (int) ($_POST['assigned_to'] ?? 0);
        $check = db()->prepare("SELECT full_name FROM users WHERE id = ? AND role IN ('technician', 'admin')");
        $check->execute([$assignee]);
        $name = $check->fetchColumn();
        if ($name !== false) {
            db()->prepare('UPDATE tickets SET assigned_to = ?, updated_at = ? WHERE id = ?')->execute([$assignee, now(), $id]);
            audit('ticket_assigned', 'ticket', $id, 'Assigned to ' . $name);
            flash('Ticket assigned to ' . $name . '.');
        }
    }

    redirect('ticket.php?id=' . $id);
}

$comments = db()->prepare(
    'SELECT tc.body, tc.created_at, u.full_name, u.role
     FROM ticket_comments tc JOIN users u ON u.id = tc.user_id
     WHERE tc.ticket_id = ? ORDER BY tc.created_at, tc.id'
);
$comments->execute([$id]);
$comments = $comments->fetchAll();

$technicians = is_staff($user)
    ? db()->query("SELECT id, full_name FROM users WHERE role IN ('technician', 'admin') ORDER BY full_name")->fetchAll()
    : [];

$title = 'Ticket #' . $id;
require __DIR__ . '/../src/views/header.php';
?>
<div class="page-head">
    <h1>#<?= $id ?> <?= e($ticket['title']) ?></h1>
    <div><?= badge($ticket['priority']) ?> <?= badge($ticket['status']) ?></div>
</div>

<div class="ticket-layout">
    <section>
        <p class="meta">Opened by <?= e($ticket['created_by_name']) ?> on <?= e(substr((string) $ticket['created_at'], 0, 16)) ?></p>
        <div class="description"><?= nl2br(e($ticket['description'])) ?></div>

        <h2>Comments</h2>
        <?php if (!$comments): ?><p class="empty">No comments yet.</p><?php endif; ?>
        <?php foreach ($comments as $c): ?>
            <article class="comment<?= in_array($c['role'], ['technician', 'admin'], true) ? ' comment-staff' : '' ?>">
                <p class="meta"><strong><?= e($c['full_name']) ?></strong> <?= e(substr((string) $c['created_at'], 0, 16)) ?></p>
                <p><?= nl2br(e($c['body'])) ?></p>
            </article>
        <?php endforeach; ?>

        <form method="post" class="stack" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="comment">
            <label>Add a comment <textarea name="body" rows="3" required></textarea></label>
            <button type="submit">Post comment</button>
        </form>
    </section>

    <aside>
        <h2>Details</h2>
        <p>Assigned to: <strong><?= e($ticket['assigned_name'] ?? 'Unassigned') ?></strong></p>

        <?php if (is_staff($user)): ?>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <label>Status
                    <select name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= label($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Update status</button>
            </form>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign">
                <label>Assign to
                    <select name="assigned_to">
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= (int) $tech['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $tech['id'] ? 'selected' : '' ?>><?= e($tech['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Assign</button>
            </form>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
