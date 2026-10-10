<?php
require_once __DIR__ . '/config.php';

$formMessage = '';
$trackMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $name = trim($_POST['name'] ?? '');
    $studentClass = trim($_POST['studentId'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $studentClass === '' || $category === '' || $subject === '' || mb_strlen($description) < 10) {
        $formMessage = 'Please complete all required fields. Description must be at least 10 characters.';
    } else {
        $trackingCode = strtoupper(bin2hex(random_bytes(6)));
        $stmt = $pdo->prepare('INSERT INTO complaints (tracking_code, student_name, student_class, section_name, category, subject, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$trackingCode, $name, $studentClass, $section, $category, $subject, $description]);
        $formMessage = 'Complaint submitted successfully! Your tracking code is ' . $trackingCode . '. Save it to check progress.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_tracking'])) {
    $code = strtoupper(trim($_POST['tracking_code'] ?? ''));
    if ($code !== '') {
        $stmt = $pdo->prepare('SELECT tracking_code, subject, status, created_at, admin_note FROM complaints WHERE tracking_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $complaint = $stmt->fetch();
        if ($complaint) {
            $trackMessage = 'Complaint: ' . $complaint['subject'] . ' | Status: ' . $complaint['status'] . ' | Submitted: ' . $complaint['created_at'];
            if (!empty($complaint['admin_note'])) $trackMessage .= ' | Staff note: ' . $complaint['admin_note'];
        } else {
            $trackMessage = 'No complaint found with that tracking code. Please check the code and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Student Affairs college complaint portal">
  <title>Student Affairs | Student Complaint Portal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="index.php"><span class="brand-mark">SA</span><span><strong>Student Affairs</strong><small>Student Complaint Portal</small></span></a>
  
</header>
<main class="container">
  <section class="hero" id="home">
    <div class="hero-copy">
      <span class="eyebrow"><i></i> YOUR VOICE MATTERS</span>
      <h1>Making your college<br><span>a better place.</span></h1>
      <p>Have a concern or suggestion? Share it with Student Affairs. Your feedback helps improve the campus for everyone.</p>
      <div class="actions"><a class="btn primary" href="#submit">Submit a complaint <b>→</b></a><a class="btn secondary" href="#track">Track a complaint</a></div>
      <div class="note"><span>✓</span> Every submission deserves attention.</div>
    </div>
    <div class="hero-art" aria-hidden="true"><div class="orbit one"></div><div class="orbit two"></div><div class="art-card"><div class="art-icon">✦</div><div class="lines"><i></i><i></i><i></i></div><div class="received">● &nbsp; Your voice is heard</div></div><div class="float top">✓ <span>Feedback received</span></div><div class="float bottom">↗ <span>Positive change starts here</span></div></div>
  </section>
  <section class="section-heading"><div><span class="eyebrow">HOW IT WORKS</span><h2>A simple way to be heard.</h2></div><p>Three easy steps to help make campus life better.</p></section>
  <section class="features">
    <article class="feature"><span class="feature-icon blue">✎</span><h3>Share your concern</h3><p>Tell us what happened and choose the category that best fits your concern.</p><em>01</em></article>
    <article class="feature"><span class="feature-icon violet">⌕</span><h3>Track progress</h3><p>Check the latest status of your complaint using your tracking code.</p><em>02</em></article>
    <article class="feature"><span class="feature-icon green">✓</span><h3>Help improve campus</h3><p>Student feedback helps the college identify issues and make improvements.</p><em>03</em></article>
  </section>
  <section class="form-section" id="submit">
    <div class="section-intro"><span class="eyebrow">STUDENT SUPPORT</span><h2>Submit a complaint</h2><p>Fill in the details below. Your complaint will receive a tracking code.</p></div>
    <form id="complaint-form" class="form-card" method="post" action="index.php#submit">
      <input type="hidden" name="submit_complaint" value="1">
      <div class="field-grid">
        <label>Full name <span>*</span><input name="name" required maxlength="120" placeholder="Your full name"></label>
        <label>Class <span>*</span><input name="studentId" required maxlength="60" placeholder="Enter your class"></label>
        <label>Section<input type="text" name="section" maxlength="60" placeholder="Enter your section"></label>
        <label>Category <span>*</span><select name="category" required><option value="">Select a category</option><option>Facilities & Classrooms</option><option>Cleanliness & Washrooms</option><option>Teaching & Academics</option><option>Administration</option><option>Transport</option><option>IT & Equipment</option><option>Safety or Harassment</option><option>Suggestion</option><option>Other</option></select></label>
        <label class="full">Subject <span>*</span><input name="subject" required maxlength="180" placeholder="Briefly describe the issue"></label>
        <label class="full">Description <span>*</span><textarea name="description" rows="5" required minlength="10" maxlength="3000" placeholder="What happened, where, and when?"></textarea></label>
      </div>
      <div class="privacy"><span>🔒</span><p>Your complaint is saved to the college complaint database. Keep your tracking code to check its status.</p></div>
      <button class="btn primary" type="submit">Submit complaint <b>→</b></button>
      <?php if ($formMessage): ?><p class="message" role="status"><?php echo htmlspecialchars($formMessage); ?></p><?php endif; ?>
    </form>
  </section>
  <section class="form-section track-section" id="track">
    <div class="section-intro"><span class="eyebrow">STATUS CHECK</span><h2>Track your complaint</h2><p>Enter the tracking code you received when you submitted your complaint.</p></div>
    <form id="track-form" class="form-card track-form" method="post" action="index.php#track">
      <input type="hidden" name="check_tracking" value="1"><label>Tracking code<input name="tracking_code" required maxlength="12" placeholder="e.g. A1B2C3D4E5F6"></label><button class="btn primary" type="submit">Check status <b>→</b></button><?php if ($trackMessage): ?><p class="message" role="status"><?php echo htmlspecialchars($trackMessage); ?></p><?php endif; ?></form>
  </section>
  <section class="bottom-cta"><div><span class="eyebrow">WE'RE LISTENING</span><h2>Have something to share?</h2><p>Help us build a better college experience for everyone.</p></div><a class="btn primary" href="#submit">Make a submission <b>→</b></a></section>
</main>
<footer><div><strong>Student Affairs</strong><span>A better campus starts with you.</span></div><small>For urgent safety concerns, contact your college administration directly. · <a href="admin.php">Staff login</a></small></footer>

</body>
</html>

