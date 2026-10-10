<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loginError = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Incorrect username or password.';
}

if (empty($_SESSION['admin_id'])):
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff Login | Student Affairs</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="admin.css"></head>
<body class="admin-body">
<header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">SA</span><span><strong>Student Affairs</strong><small>Staff administration</small></span></a><a class="btn secondary" href="index.php">← Student website</a></header>
<main class="admin-wrap"><section class="admin-login"><span class="eyebrow">RESTRICTED ACCESS</span><h1>Staff login</h1><p>Sign in to review and manage student complaints.</p>
<?php if ($loginError): ?><p class="admin-alert"><?php echo htmlspecialchars($loginError); ?></p><?php endif; ?>
<form method="post" class="admin-form"><input type="hidden" name="login" value="1"><label>Username<input name="username" required autocomplete="username" placeholder="Admin username"></label><label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Password"></label><button class="btn primary" type="submit">Sign in →</button></form>
<p class="admin-hint">First-time local login: <strong>admin</strong> / <strong>ChangeMe123!</strong>. Change this before any real deployment.</p></section></main>
</body></html>
<?php
exit;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    $id = (int)($_POST['complaint_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $note = trim($_POST['admin_note'] ?? '');
    if (in_array($status, ['Pending', 'In Progress', 'Resolved'], true)) {
        $stmt = $pdo->prepare('UPDATE complaints SET status = ?, admin_note = ? WHERE id = ?');
        $stmt->execute([$status, $note, $id]);
        $notice = 'Complaint updated.';
    }
}
$filter = $_GET['status'] ?? '';
$allowedFilters = ['Pending', 'In Progress', 'Resolved'];
if (in_array($filter, $allowedFilters, true)) {
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE status = ? ORDER BY created_at DESC');
    $stmt->execute([$filter]);
    $complaints = $stmt->fetchAll();
} else {
    $complaints = $pdo->query('SELECT * FROM complaints ORDER BY created_at DESC')->fetchAll();
}
$total = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Pending'")->fetchColumn();
$progress = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolved = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Dashboard | Student Affairs</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="admin.css"></head>
<body class="admin-body">
<header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">SA</span><span><strong>Student Affairs</strong><small>Admin dashboard</small></span></a><div class="admin-header-right"><span>Signed in as <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span><a class="btn secondary" href="admin.php?logout=1">Log out</a></div></header>
<main class="admin-dashboard"><div class="admin-title"><div><span class="eyebrow">STUDENT SUPPORT</span><h1>Complaints dashboard</h1><p>Review submissions and update their progress.</p></div><a class="btn secondary" href="index.php">View student site ↗</a></div>
<?php if ($notice): ?><p class="admin-success"><?php echo htmlspecialchars($notice); ?></p><?php endif; ?>
<div class="stats-grid"><div class="stat-card"><span>Total complaints</span><strong><?php echo $total; ?></strong></div><div class="stat-card"><span>Pending</span><strong><?php echo $pending; ?></strong></div><div class="stat-card"><span>In progress</span><strong><?php echo $progress; ?></strong></div><div class="stat-card"><span>Resolved</span><strong><?php echo $resolved; ?></strong></div></div>
<div class="complaints-heading"><h2>Submissions</h2><div class="filters"><a href="admin.php" class="<?php echo $filter === '' ? 'active' : ''; ?>">All</a><?php foreach ($allowedFilters as $s): ?><a class="<?php echo $filter === $s ? 'active' : ''; ?>" href="admin.php?status=<?php echo urlencode($s); ?>"><?php echo htmlspecialchars($s); ?></a><?php endforeach; ?></div></div>
<?php if (!$complaints): ?><div class="empty-state">No complaints here yet.</div><?php else: ?>
<div class="complaint-list">
<?php foreach ($complaints as $c): ?><article class="complaint-card"><div class="complaint-top"><div><span class="tracking">Code: <?php echo htmlspecialchars($c['tracking_code']); ?></span><h3><?php echo htmlspecialchars($c['subject']); ?></h3><p><?php echo htmlspecialchars($c['student_name']); ?> · Class <?php echo htmlspecialchars($c['student_class']); ?><?php echo $c['section_name'] !== '' ? ' · Section ' . htmlspecialchars($c['section_name']) : ''; ?></p></div><span class="status-pill <?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>"><?php echo htmlspecialchars($c['status']); ?></span></div>
<div class="complaint-meta"><span><?php echo htmlspecialchars($c['category']); ?></span><span><?php echo htmlspecialchars($c['created_at']); ?></span></div><p class="complaint-description"><?php echo nl2br(htmlspecialchars($c['description'])); ?></p>
<form method="post" class="update-form"><input type="hidden" name="update_complaint" value="1"><input type="hidden" name="complaint_id" value="<?php echo (int)$c['id']; ?>"><label>Status<select name="status"><?php foreach ($allowedFilters as $s): ?><option <?php echo $c['status'] === $s ? 'selected' : ''; ?> value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?></select></label><label class="note-field">Staff note (optional)<input name="admin_note" maxlength="2000" value="<?php echo htmlspecialchars($c['admin_note'] ?? ''); ?>" placeholder="Add an update for the student"></label><button class="btn primary" type="submit">Save update</button></form></article><?php endforeach; ?>
</div><?php endif; ?>
</main></body></html>
