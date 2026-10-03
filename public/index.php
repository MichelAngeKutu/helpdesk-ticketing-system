<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();

// Staff, and users granted "tickets.view_all", see every ticket.
// Everyone else only sees tickets they created.
$seeAll = is_staff($user) || has_permission($user, 'tickets.view_all');

$sql = 'SELECT t.id, t.title, t.priority, t.status, t.updated_at,
               c.full_name AS created_by_name, a.full_name AS assigned_name
        FROM tickets t
        JOIN users c ON c.id = t.created_by
        LEFT JOIN users a ON a.id = t.assigned_to';
$params = [];
if (!$seeAll) {
    $sql .= ' WHERE t.created_by = ?';
    $params[] = $user['id'];
}
$sql .= ' ORDER BY t.updated_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$title = 'Tickets';
require __DIR__ . '/../src/views/header.php';
?>
<div class="page-head">
    <h1><?= $seeAll ? 'All tickets' : 'My tickets' ?></h1>
    <a class="button" href="ticket_new.php">New ticket</a>
</div>

<?php if (!$tickets): ?>
    <p class="empty">No tickets yet. <a href="ticket_new.php">Create the first one</a>.</p>
<?php else: ?>
    <div class="filters">
        <input type="search" id="ticket-search" placeholder="Search by title or person" aria-label="Search tickets">
        <select id="status-filter" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="open">Open</option>
            <option value="in_progress">In progress</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
        </select>
    </div>
    <div class="table-wrap">
    <table id="ticket-table">
        <thead>
            <tr><th>#</th><th>Title</th><th>Priority</th><th>Status</th><th>Created by</th><th>Assigned to</th><th>Updated</th></tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr data-status="<?= e($t['status']) ?>">
                <td><?= (int) $t['id'] ?></td>
                <td><a href="ticket.php?id=<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a></td>
                <td><?= badge($t['priority']) ?></td>
                <td><?= badge($t['status']) ?></td>
                <td><?= e($t['created_by_name']) ?></td>
                <td><?= e($t['assigned_name'] ?? 'Unassigned') ?></td>
                <td><?= e(substr((string) $t['updated_at'], 0, 16)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="empty" id="no-matches" hidden>No tickets match your search.</p>
<?php endif; ?>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
