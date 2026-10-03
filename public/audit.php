<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();
require_role($user, 'admin');

// Newest first; show the latest 200 events
$stmt = db()->query(
    'SELECT al.created_at, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, u.username
     FROM audit_log al LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.id DESC'
);
$events = [];
while (count($events) < 200 && ($row = $stmt->fetch())) {
    $events[] = $row;
}
$stmt->closeCursor();

$title = 'Audit log';
require __DIR__ . '/../src/views/header.php';
?>
<div class="page-head">
    <h1>Audit log</h1>
    <input type="search" id="audit-search" placeholder="Filter events" aria-label="Filter audit events">
</div>
<div class="table-wrap">
<table id="audit-table">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
        <tr>
            <td><?= e(substr((string) $ev['created_at'], 0, 19)) ?></td>
            <td><?= e($ev['username'] ?? 'unknown') ?></td>
            <td><?= e(label($ev['action'])) ?></td>
            <td><?= $ev['entity_type'] ? e($ev['entity_type'] . ' #' . $ev['entity_id']) : '' ?></td>
            <td><?= e($ev['details']) ?></td>
            <td><?= e($ev['ip_address']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../src/views/footer.php'; ?>
