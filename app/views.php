<?php
$topics = [
    'Academic records',
    'Course registration',
    'Lectures and timetable',
    'Examinations',
    'Fees and payments',
    'Student affairs',
    'ICT and student portal',
    'Facilities and safety',
    'Other',
];
$allDepartments = $pdo->query('SELECT d.id, d.name, s.name AS school_name FROM departments d JOIN schools s ON s.id = d.school_id ORDER BY s.name, d.name')->fetchAll();

function ticket_rows(PDO $pdo, string $where, array $parameters): array
{
    $query = $pdo->prepare(
        'SELECT t.id, t.reference, t.department_id, t.topic, t.course_name, t.request, t.status, t.created_at, t.updated_at,
                u.fullname AS student_name, u.matric_number, d.name AS department_name
         FROM tickets t JOIN users u ON u.id = t.student_id
         LEFT JOIN departments d ON d.id = t.department_id ' . $where . '
         ORDER BY t.created_at DESC LIMIT 100'
    );
    $query->execute($parameters);
    return $query->fetchAll();
}

function render_ticket_table(PDO $pdo, array $tickets, string $viewRole, array $departments = []): void
{
    if (!$tickets) {
        echo '<div class="empty-state"><span class="empty-icon"><i class="ti ti-inbox"></i></span><h5>No tickets to show</h5><p>New complaints will appear here when they are submitted.</p></div>';
        return;
    }
    ?>
    <div class="table-responsive">
      <table class="table align-middle app-table">
        <thead><tr><th>Reference / student</th><th>Complaint</th><th>Department</th><th>Status</th><th>Submitted</th><?php if ($viewRole !== 'student'): ?><th>Action</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><a class="ticket-ref" href="<?= h(url('page=track&reference=' . rawurlencode($ticket['reference']))) ?>"><?= h($ticket['reference']) ?></a><span class="table-sub"><?= h($ticket['student_name']) ?><?= $ticket['matric_number'] ? ' · ' . h($ticket['matric_number']) : '' ?></span></td>
            <td><b><?= h($ticket['topic']) ?></b><span class="table-sub"><?= h($ticket['course_name']) ?></span></td>
            <td><?= h($ticket['department_name'] ?: 'Not assigned') ?></td>
            <td><?= status_badge($ticket['status']) ?></td>
            <td><?= h(date('M j, Y', strtotime($ticket['created_at']))) ?></td>
            <?php if ($viewRole !== 'student'): ?>
            <td>
              <details class="ticket-actions">
                <summary>Open case</summary>
                <div class="ticket-action-panel">
                  <p class="ticket-request"><?= nl2br(h($ticket['request'])) ?></p>
                  <?php if ($viewRole === 'admin'): ?>
                  <form method="post" class="mb-3">
                    <?= csrf_field() ?><input type="hidden" name="action" value="route_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <label class="form-label">Route to department</label>
                    <div class="d-flex gap-2"><select class="form-select form-select-sm" name="department_id" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (int) $department['id'] === (int) ($ticket['department_id'] ?? 0) ? 'selected' : '' ?>><?= h($department['school_name'] . ' · ' . $department['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary" type="submit">Route</button></div>
                  </form>
                  <?php endif; ?>
                  <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <label class="form-label">Status</label>
                    <select class="form-select form-select-sm mb-2" name="status"><option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option><option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option><option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option></select>
                    <label class="form-label">Update for the student</label><textarea class="form-control form-control-sm mb-2" name="message" rows="3" minlength="3" maxlength="5000" required placeholder="Write a response or explain what happens next"></textarea>
                    <button class="btn btn-sm btn-primary" type="submit">Save update</button>
                  </form>
                </div>
              </details>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

switch ($page):
case 'home':
?>
<section class="hero-section">
  <div class="hero-copy">
    <div class="eyebrow"><span class="eyebrow-dot"></span> FEDERAL COLLEGE OF WILDLIFE MANAGEMENT</div>
    <h1>Your voice matters.<br><span>We’re here to help.</span></h1>
    <p>One place to raise a concern, get it to the right department, and follow every update until it’s resolved.</p>
    <div class="hero-actions"><a class="btn btn-primary btn-lg" href="<?= h(url('page=register')) ?>">Create a student account <i class="ti ti-arrow-right ms-1"></i></a><a class="btn btn-light btn-lg" href="<?= h(url('page=track')) ?>">Track a complaint</a></div>
    <div class="hero-note"><i class="ti ti-shield-check"></i> Your complaint is handled by the right college team.</div>
  </div>
  <div class="hero-visual">
    <div class="hero-illustration"><div class="sun-disc"></div><div class="hill hill-back"></div><div class="hill hill-front"></div><div class="tree tree-one"><i class="ti ti-tree"></i></div><div class="tree tree-two"><i class="ti ti-tree"></i></div><div class="hero-ticket"><span class="mini-check"><i class="ti ti-check"></i></span><div><b>Support request</b><small>Delivered to the right team</small></div><span class="hero-status">On its way</span></div></div>
    <div class="floating-note"><span class="floating-icon"><i class="ti ti-message-circle-check"></i></span><span><b>Every update,</b><small>all in one place</small></span></div>
  </div>
</section>
<section class="feature-strip"><div><span class="feature-icon"><i class="ti ti-file-description"></i></span><span><b>Tell us what’s going on</b><small>Choose a topic and describe your concern.</small></span></div><div><span class="feature-icon"><i class="ti ti-route"></i></span><span><b>Sent to the right team</b><small>Your complaint reaches the responsible department.</small></span></div><div><span class="feature-icon"><i class="ti ti-progress-check"></i></span><span><b>Follow your case</b><small>Use your reference number to see progress.</small></span></div></section>
<section class="home-bottom"><div><span class="eyebrow">STUDENT SUPPORT PORTAL</span><h2>A clearer path to getting help.</h2><p>Already submitted a complaint? Check its status and read the latest response.</p></div><a class="text-link" href="<?= h(url('page=track')) ?>">Track your ticket <i class="ti ti-arrow-up-right"></i></a></section>
<?php
break;

case 'setup':
?>
<section class="auth-shell"><div class="auth-card">
  <span class="auth-symbol"><i class="ti ti-settings"></i></span><div class="eyebrow">ONE-TIME SETUP</div><h1>Create the administrator</h1>
  <p class="text-muted">The database is ready. Set up the first administrator account to manage complaint routing and department access.</p>
  <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_admin">
    <label class="form-label" for="fullname">Full name</label><input class="form-control mb-3" id="fullname" name="fullname" autocomplete="name" required>
    <label class="form-label" for="email">Administrator email</label><input class="form-control mb-3" id="email" name="email" type="email" autocomplete="email" required>
    <label class="form-label" for="password">Password <span class="text-muted">(at least 10 characters)</span></label><input class="form-control mb-4" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
    <button class="btn btn-primary w-100" type="submit">Create administrator</button>
  </form>
  <div class="auth-footnote"><i class="ti ti-lock"></i> Setup closes automatically after the first administrator is created.</div>
</div></section>
<?php
break;

case 'login':
?>
<section class="auth-shell"><div class="auth-card">
  <span class="auth-symbol"><i class="ti ti-login-2"></i></span><div class="eyebrow">WELCOME BACK</div><h1>Sign in to your account</h1>
  <p class="text-muted">Students can sign in with their email or matric number. Staff can use their registered email.</p>
  <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="login">
    <label class="form-label" for="identifier">Email address or matric number</label><input class="form-control mb-3" id="identifier" name="identifier" autocomplete="username" required>
    <label class="form-label" for="password">Password</label><input class="form-control mb-4" id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn btn-primary w-100" type="submit">Sign in</button>
  </form>
  <div class="auth-footnote">New student? <a href="<?= h(url('page=register')) ?>">Create an account</a></div>
</div></section>
<?php
break;

case 'register':
?>
<section class="auth-shell"><div class="auth-card auth-card-wide">
  <span class="auth-symbol"><i class="ti ti-user-plus"></i></span><div class="eyebrow">STUDENT REGISTRATION</div><h1>Create your account</h1><p class="text-muted">Use your college details to open and follow support requests.</p>
  <form method="post" class="app-form" id="register-form"><?= csrf_field() ?><input type="hidden" name="action" value="register">
    <label class="form-label" for="fullname">Full name</label><input class="form-control mb-3" id="fullname" name="fullname" autocomplete="name" required>
    <div class="row"><div class="col-md-6"><label class="form-label" for="email">Email address</label><input class="form-control mb-3" id="email" name="email" type="email" autocomplete="email" required></div><div class="col-md-6"><label class="form-label" for="matric_number">Matric number</label><input class="form-control mb-3 text-uppercase" id="matric_number" name="matric_number" required></div></div>
    <div class="row"><div class="col-md-6"><label class="form-label" for="school_id">School</label><select class="form-select mb-3" id="school_id" name="school_id" required><option value="">Select your school</option><?php foreach ($schools as $school): ?><option value="<?= (int) $school['id'] ?>"><?= h($school['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label" for="department_id">Department</label><select class="form-select mb-3" id="department_id" name="department_id" data-department-select disabled required><option value="">Select a school first</option></select></div></div>
    <label class="form-label" for="password">Password <span class="text-muted">(at least 8 characters)</span></label><input class="form-control mb-4" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
    <button class="btn btn-primary w-100" type="submit">Create student account</button>
  </form><div class="auth-footnote">Already registered? <a href="<?= h(url('page=login')) ?>">Sign in</a></div>
</div></section>
<?php
break;

case 'track':
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$tracked = null;
$history = [];
if ($reference !== '') {
    $ticketQuery = $pdo->prepare('SELECT t.*, d.name AS department_name FROM tickets t LEFT JOIN departments d ON d.id = t.department_id WHERE t.reference = ?');
    $ticketQuery->execute([$reference]);
    $tracked = $ticketQuery->fetch();
    if ($tracked) {
        $historyQuery = $pdo->prepare('SELECT tu.status, tu.message, tu.created_at, u.fullname, u.role FROM ticket_updates tu LEFT JOIN users u ON u.id = tu.user_id WHERE tu.ticket_id = ? ORDER BY tu.created_at DESC, tu.id DESC');
        $historyQuery->execute([$tracked['id']]);
        $history = $historyQuery->fetchAll();
    }
}
?>
<section class="track-page">
  <div class="track-intro"><span class="eyebrow">COMPLAINT TRACKING</span><h1>Know where things stand.</h1><p>Enter the reference number you received after submitting your complaint.</p></div>
  <div class="track-search-card"><form method="get" action="<?= h(url()) ?>"><input type="hidden" name="page" value="track"><label class="form-label" for="reference">Ticket reference number</label><div class="track-search-row"><input class="form-control" id="reference" name="reference" placeholder="e.g. FCWM-2610-A1B2C3D4" value="<?= h($reference) ?>" required><button class="btn btn-primary" type="submit"><i class="ti ti-search me-1"></i> Track ticket</button></div><small class="text-muted mt-2 d-block">Your reference number is private. Keep it safe so only you can access your updates.</small></form></div>
  <?php if ($reference !== '' && !$tracked): ?><div class="alert alert-warning mt-4">We couldn’t find a complaint with that reference. Check the number and try again.</div><?php endif; ?>
  <?php if ($tracked): ?>
  <div class="track-result">
    <div class="track-result-head"><div><small>SUPPORT REQUEST</small><h3><?= h($tracked['reference']) ?></h3></div><?= status_badge($tracked['status']) ?></div>
    <div class="track-facts"><div><small>Topic</small><b><?= h($tracked['topic']) ?></b></div><div><small>Course</small><b><?= h($tracked['course_name']) ?></b></div><div><small>Assigned department</small><b><?= h($tracked['department_name'] ?: 'Awaiting assignment') ?></b></div><div><small>Submitted</small><b><?= h(formatted_date($tracked['created_at'])) ?></b></div></div>
    <h5 class="timeline-heading">Updates <span><?= count($history) ?></span></h5>
    <?php if (!$history): ?><p class="text-muted">There are no updates yet.</p><?php else: ?><div class="timeline"><?php foreach ($history as $item): ?><article class="timeline-item"><span class="timeline-dot"></span><div><div class="timeline-meta"><b><?= h($item['role'] === 'student' ? 'You' : ($item['fullname'] ?: 'Support team')) ?></b><?php if ($item['status']): ?><?= status_badge($item['status']) ?><?php endif; ?><time><?= h(formatted_date($item['created_at'])) ?></time></div><p><?= nl2br(h($item['message'])) ?></p></div></article><?php endforeach; ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php
break;

case 'dashboard':
if ($role === 'student') {
    $statsQuery = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status IN ('pending','in_progress')) AS open_count, SUM(status = 'resolved') AS resolved_count FROM tickets WHERE student_id = ?");
    $statsQuery->execute([$current['id']]);
    $stats = $statsQuery->fetch();
    $recent = ticket_rows($pdo, 'WHERE t.student_id = ?', [$current['id']]);
    $departmentQuery = $pdo->prepare('SELECT d.name FROM departments d WHERE d.id = ?');
    $departmentQuery->execute([$current['department_id']]);
    $departmentName = (string) $departmentQuery->fetchColumn();
?>
<div class="page-heading"><div><div class="eyebrow">STUDENT WORKSPACE</div><h1>Good to see you, <?= h(explode(' ', trim($current['fullname']))[0]) ?>.</h1><p>Raise a concern or check on a complaint you’ve already sent.</p></div><a href="<?= h(url('page=new-ticket')) ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i> New complaint</a></div>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="metric-card"><span class="metric-icon metric-purple"><i class="ti ti-files"></i></span><div><small>Total complaints</small><strong><?= (int) $stats['total'] ?></strong></div></div></div>
  <div class="col-md-4"><div class="metric-card"><span class="metric-icon metric-amber"><i class="ti ti-clock"></i></span><div><small>Awaiting resolution</small><strong><?= (int) $stats['open_count'] ?></strong></div></div></div>
  <div class="col-md-4"><div class="metric-card"><span class="metric-icon metric-green"><i class="ti ti-circle-check"></i></span><div><small>Resolved</small><strong><?= (int) $stats['resolved_count'] ?></strong></div></div></div>
</div>
<div class="callout-card"><span class="feature-icon"><i class="ti ti-school"></i></span><div><b><?= h($departmentName ?: 'Student') ?></b><p>Your student account is connected to its department, so you can select the right course when you submit a request.</p></div><a href="<?= h(url('page=track')) ?>">Track a ticket <i class="ti ti-arrow-right"></i></a></div>
<div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Recent complaints</h4><p>Your latest support requests and their status.</p></div><a href="<?= h(url('page=my-tickets')) ?>">View all</a></div><?php render_ticket_table($pdo, array_slice($recent, 0, 5), 'student'); ?></div></div>
<?php
} elseif ($role === 'admin') {
    $stats = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending_count, SUM(status = 'in_progress') AS progress_count, SUM(status = 'resolved') AS resolved_count FROM tickets")->fetch();
    $recent = ticket_rows($pdo, '', []);
?>
<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Support overview</h1><p>Review incoming complaints and get them to the right department.</p></div><a href="<?= h(url('page=inbox')) ?>" class="btn btn-primary"><i class="ti ti-route me-1"></i> Route complaints</a></div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-purple"><i class="ti ti-files"></i></span><div><small>Total tickets</small><strong><?= (int) $stats['total'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-amber"><i class="ti ti-clock"></i></span><div><small>Pending</small><strong><?= (int) $stats['pending_count'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-blue"><i class="ti ti-loader-2"></i></span><div><small>In progress</small><strong><?= (int) $stats['progress_count'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-green"><i class="ti ti-circle-check"></i></span><div><small>Resolved</small><strong><?= (int) $stats['resolved_count'] ?></strong></div></div></div>
</div>
<div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Latest complaints</h4><p>The most recently submitted student requests.</p></div><a href="<?= h(url('page=inbox')) ?>">Open routing inbox</a></div><?php render_ticket_table($pdo, array_slice($recent, 0, 8), 'admin', $allDepartments); ?></div></div>
<?php
} else {
    $statsQuery = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending_count, SUM(status = 'in_progress') AS progress_count, SUM(status = 'resolved') AS resolved_count FROM tickets WHERE department_id = ?");
    $statsQuery->execute([$current['department_id']]);
    $stats = $statsQuery->fetch();
    $recent = ticket_rows($pdo, 'WHERE t.department_id = ?', [$current['department_id']]);
    $departmentQuery = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $departmentQuery->execute([$current['department_id']]);
    $departmentName = (string) $departmentQuery->fetchColumn();
?>
<div class="page-heading"><div><div class="eyebrow">DEPARTMENT WORKSPACE</div><h1><?= h($departmentName) ?></h1><p>Review cases routed to your department and keep students informed.</p></div><a href="<?= h(url('page=department-inbox')) ?>" class="btn btn-primary"><i class="ti ti-inbox me-1"></i> Open department cases</a></div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-purple"><i class="ti ti-files"></i></span><div><small>Assigned cases</small><strong><?= (int) $stats['total'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-amber"><i class="ti ti-clock"></i></span><div><small>Pending</small><strong><?= (int) $stats['pending_count'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-blue"><i class="ti ti-loader-2"></i></span><div><small>In progress</small><strong><?= (int) $stats['progress_count'] ?></strong></div></div></div>
  <div class="col-md-3"><div class="metric-card"><span class="metric-icon metric-green"><i class="ti ti-circle-check"></i></span><div><small>Resolved</small><strong><?= (int) $stats['resolved_count'] ?></strong></div></div></div>
</div>
<div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Recently assigned cases</h4><p>Only cases routed to your department are shown.</p></div><a href="<?= h(url('page=department-inbox')) ?>">View inbox</a></div><?php render_ticket_table($pdo, array_slice($recent, 0, 8), 'department'); ?></div></div>
<?php
}
break;

case 'new-ticket':
require_role('student');
$courseQuery = $pdo->prepare('SELECT id, code, name FROM courses WHERE department_id = ? ORDER BY code');
$courseQuery->execute([$current['department_id']]);
$studentCourses = $courseQuery->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">STUDENT SUPPORT</div><h1>Tell us how we can help.</h1><p>Give us enough detail to get your request to the right people.</p></div><a href="<?= h(url('page=my-tickets')) ?>" class="btn btn-light"><i class="ti ti-inbox me-1"></i> My complaints</a></div>
<div class="form-layout"><div class="card app-card"><div class="card-body">
  <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_ticket">
    <div class="form-section-label"><span>01</span><div><b>About your concern</b><small>Choose the closest match for this request.</small></div></div>
    <label class="form-label" for="topic">Complaint topic</label><select class="form-select mb-4" id="topic" name="topic" required><option value="">Select a topic</option><?php foreach ($topics as $topic): ?><option value="<?= h($topic) ?>"><?= h($topic) ?></option><?php endforeach; ?></select>
    <label class="form-label" for="course_id">Course</label>
    <select class="form-select mb-1" id="course_id" name="course_id" data-course-select data-department-id="<?= (int) $current['department_id'] ?>" <?= $studentCourses ? '' : 'disabled' ?>>
      <?php if (!$studentCourses): ?><option value="">No courses available yet</option><?php else: ?><option value="">Choose a course</option><?php foreach ($studentCourses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= h($course['code'] . ' · ' . $course['name']) ?></option><?php endforeach; ?><?php endif; ?>
    </select>
    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="general_course" name="general_course" value="1" <?= $studentCourses ? '' : 'checked' ?>><label class="form-check-label" for="general_course">This request is not about a specific course</label></div>
    <div class="form-section-label"><span>02</span><div><b>Describe your request</b><small>Include relevant details so the support team can respond.</small></div></div>
    <label class="form-label" for="request">What happened, and what help do you need?</label><textarea class="form-control mb-2" id="request" name="request" rows="7" minlength="15" maxlength="10000" placeholder="Describe the issue, when it happened, and what outcome you are looking for…" required></textarea>
    <div class="d-flex justify-content-between align-items-center mb-4"><small class="text-muted">Please don’t include your password or other sensitive information.</small><small class="text-muted"><span data-character-count>0</span>/10,000</small></div>
    <button class="btn btn-primary" type="submit"><i class="ti ti-send me-1"></i> Submit complaint</button>
  </form>
</div></div><aside class="form-help-card"><span class="feature-icon"><i class="ti ti-info-circle"></i></span><h5>What happens next?</h5><ol><li>Your ticket receives a unique reference number.</li><li>An administrator routes it to the right department.</li><li>The department reviews your case and posts updates.</li><li>Track progress anytime with your reference number.</li></ol><a href="<?= h(url('page=track')) ?>">Already have a reference? Track it <i class="ti ti-arrow-right"></i></a></aside></div>
<?php
break;

case 'my-tickets':
require_role('student');
$studentTickets = ticket_rows($pdo, 'WHERE t.student_id = ?', [$current['id']]);
?>
<div class="page-heading"><div><div class="eyebrow">STUDENT SUPPORT</div><h1>My complaints</h1><p>Keep track of every request you’ve sent to the college.</p></div><a href="<?= h(url('page=new-ticket')) ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i> New complaint</a></div>
<div class="card app-card"><div class="card-body"><?php render_ticket_table($pdo, $studentTickets, 'student'); ?></div></div>
<?php
break;

case 'inbox':
require_role('admin');
$adminTickets = ticket_rows($pdo, '', []);
?>
<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Route &amp; manage complaints</h1><p>Assign unallocated complaints and monitor department responses.</p></div></div>
<div class="alert app-info-alert"><i class="ti ti-route"></i><span>Choose the responsible department from a case’s <b>Open case</b> panel. Staff can then view and update the assigned case.</span></div>
<div class="card app-card"><div class="card-body"><?php render_ticket_table($pdo, $adminTickets, 'admin', $allDepartments); ?></div></div>
<?php
break;

case 'department-inbox':
require_role('department');
$departmentTickets = ticket_rows($pdo, 'WHERE t.department_id = ?', [$current['department_id']]);
?>
<div class="page-heading"><div><div class="eyebrow">DEPARTMENT WORKSPACE</div><h1>Your department cases</h1><p>Only complaints routed to your department appear in this inbox.</p></div></div>
<div class="card app-card"><div class="card-body"><?php render_ticket_table($pdo, $departmentTickets, 'department'); ?></div></div>
<?php
break;

case 'staff':
require_role('admin');
$staffRows = $pdo->query("SELECT u.fullname, u.email, u.created_at, d.name AS department_name, s.name AS school_name FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN schools s ON s.id = d.school_id WHERE u.role = 'department' ORDER BY s.name, d.name, u.fullname")->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Department accounts</h1><p>Create staff access for a department. Each account only sees cases routed to its department.</p></div></div>
<div class="row g-4"><div class="col-lg-5"><div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Add department staff</h4><p>Create a sign-in account for a department staff member.</p></div></div>
  <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_staff">
    <label class="form-label" for="fullname">Full name</label><input class="form-control mb-3" id="fullname" name="fullname" required>
    <label class="form-label" for="email">Email address</label><input class="form-control mb-3" id="email" name="email" type="email" required>
    <label class="form-label" for="department_id">Department</label><select class="form-select mb-3" id="department_id" name="department_id" required><option value="">Select department</option><?php foreach ($allDepartments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= h($department['school_name'] . ' · ' . $department['name']) ?></option><?php endforeach; ?></select>
    <label class="form-label" for="password">Temporary password <span class="text-muted">(at least 8 characters)</span></label><input class="form-control mb-4" id="password" name="password" type="password" minlength="8" required>
    <button class="btn btn-primary" type="submit">Create department account</button>
  </form>
</div></div></div>
<div class="col-lg-7"><div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Active staff accounts</h4><p><?= count($staffRows) ?> department account<?= count($staffRows) === 1 ? '' : 's' ?></p></div></div>
  <?php if (!$staffRows): ?><div class="empty-state"><span class="empty-icon"><i class="ti ti-users"></i></span><h5>No department accounts</h5><p>Create the first staff account using the form.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle app-table"><thead><tr><th>Name</th><th>Department</th><th>Email</th></tr></thead><tbody><?php foreach ($staffRows as $staffRow): ?><tr><td><b><?= h($staffRow['fullname']) ?></b><span class="table-sub">Since <?= h(date('M Y', strtotime($staffRow['created_at']))) ?></span></td><td><?= h($staffRow['department_name'] ?: 'Unassigned') ?></td><td><?= h($staffRow['email']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></div></div></div>
<?php
break;

case 'courses':
require_role('admin', 'department');
$courseParameters = [];
$courseWhere = '';
if ($role === 'department') {
    $courseWhere = 'WHERE c.department_id = ?';
    $courseParameters[] = $current['department_id'];
}
$courseListQuery = $pdo->prepare('SELECT c.id, c.code, c.name, d.name AS department_name, s.name AS school_name FROM courses c JOIN departments d ON d.id = c.department_id JOIN schools s ON s.id = d.school_id ' . $courseWhere . ' ORDER BY s.name, d.name, c.code');
$courseListQuery->execute($courseParameters);
$courseRows = $courseListQuery->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow"><?= $role === 'admin' ? 'ADMINISTRATION' : 'DEPARTMENT WORKSPACE' ?></div><h1>Course directory</h1><p>Students select from this list when they submit a course-related complaint.</p></div></div>
<div class="row g-4"><div class="col-lg-5"><div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Add a course</h4><p>Keep your department’s course options up to date.</p></div></div>
  <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_course">
    <?php if ($role === 'admin'): ?><label class="form-label" for="department_id">Department</label><select class="form-select mb-3" id="department_id" name="department_id" required><option value="">Select department</option><?php foreach ($allDepartments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= h($department['school_name'] . ' · ' . $department['name']) ?></option><?php endforeach; ?></select><?php else: ?><div class="assigned-department"><i class="ti ti-building-community"></i> Courses will be added to your department only.</div><?php endif; ?>
    <label class="form-label" for="code">Course code</label><input class="form-control mb-3 text-uppercase" id="code" name="code" maxlength="30" placeholder="e.g. WLM 201" required>
    <label class="form-label" for="name">Course title</label><input class="form-control mb-4" id="name" name="name" maxlength="160" placeholder="e.g. Principles of Wildlife Management" required>
    <button class="btn btn-primary" type="submit">Add course</button>
  </form>
</div></div></div><div class="col-lg-7"><div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Available courses</h4><p><?= count($courseRows) ?> course<?= count($courseRows) === 1 ? '' : 's' ?> listed</p></div></div>
  <?php if (!$courseRows): ?><div class="empty-state"><span class="empty-icon"><i class="ti ti-books"></i></span><h5>No courses added yet</h5><p>Add department courses so students can select them on the complaint form.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle app-table"><thead><tr><th>Course</th><th>School</th><th>Department</th></tr></thead><tbody><?php foreach ($courseRows as $courseRow): ?><tr><td><b><?= h($courseRow['code']) ?></b><span class="table-sub"><?= h($courseRow['name']) ?></span></td><td><?= h($courseRow['school_name']) ?></td><td><?= h($courseRow['department_name']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></div></div></div>
<?php
break;

case 'organization':
require_role('admin');
$schoolRows = $pdo->query('SELECT s.id, s.name, COUNT(d.id) AS department_count FROM schools s LEFT JOIN departments d ON d.school_id = s.id GROUP BY s.id, s.name ORDER BY s.name')->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Schools &amp; departments</h1><p>Set up the college structure used by student registration and department routing.</p></div></div>
<div class="alert app-info-alert"><i class="ti ti-info-circle"></i><span>Review the starter entries against the college’s official structure. Add missing schools and departments here before directing students to register.</span></div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card app-card mb-4"><div class="card-body"><div class="section-title"><div><h4>Add a school</h4><p>Schools appear as the first choice during registration.</p></div></div>
      <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_school"><label class="form-label" for="school-name">School name</label><input class="form-control mb-3" id="school-name" name="name" maxlength="150" required><button class="btn btn-primary" type="submit">Add school</button></form>
    </div></div>
    <div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Add a department</h4><p>Departments belong to one school.</p></div></div>
      <form method="post" class="app-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_department"><label class="form-label" for="department-school">School</label><select class="form-select mb-3" id="department-school" name="school_id" required><option value="">Select school</option><?php foreach ($schools as $school): ?><option value="<?= (int) $school['id'] ?>"><?= h($school['name']) ?></option><?php endforeach; ?></select><label class="form-label" for="department-name">Department name</label><input class="form-control mb-3" id="department-name" name="name" maxlength="150" required><button class="btn btn-primary" type="submit">Add department</button></form>
    </div></div>
  </div>
  <div class="col-lg-7"><div class="card app-card"><div class="card-body"><div class="section-title"><div><h4>Current college structure</h4><p><?= count($schoolRows) ?> schools listed</p></div></div>
    <?php foreach ($schoolRows as $schoolRow): ?>
    <?php $departmentList = $pdo->prepare('SELECT id, name FROM departments WHERE school_id = ? ORDER BY name'); $departmentList->execute([$schoolRow['id']]); $schoolDepartments = $departmentList->fetchAll(); ?>
    <div class="organization-school">
      <form method="post" class="organization-edit-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_school"><input type="hidden" name="school_id" value="<?= (int) $schoolRow['id'] ?>"><input class="form-control form-control-sm" name="name" value="<?= h($schoolRow['name']) ?>" maxlength="150" aria-label="School name" required><span><?= (int) $schoolRow['department_count'] ?> department<?= (int) $schoolRow['department_count'] === 1 ? '' : 's' ?></span><button class="btn btn-sm btn-light" type="submit">Save</button></form>
      <?php if ($schoolDepartments): ?>
      <div class="organization-departments"><?php foreach ($schoolDepartments as $departmentRow): ?>
        <form method="post" class="organization-edit-form organization-department-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_department"><input type="hidden" name="department_id" value="<?= (int) $departmentRow['id'] ?>"><select class="form-select form-select-sm" name="school_id" aria-label="Department school"><?php foreach ($schools as $school): ?><option value="<?= (int) $school['id'] ?>" <?= (int) $school['id'] === (int) $schoolRow['id'] ? 'selected' : '' ?>><?= h($school['name']) ?></option><?php endforeach; ?></select><input class="form-control form-control-sm" name="name" value="<?= h($departmentRow['name']) ?>" maxlength="150" aria-label="Department name" required><button class="btn btn-sm btn-light" type="submit">Save</button></form>
      <?php endforeach; ?></div>
      <?php else: ?><p class="text-muted">No departments yet. Add one to allow student registration.</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div></div></div>
</div>
<?php
break;

default:
?>
<section class="auth-shell"><div class="auth-card"><span class="auth-symbol"><i class="ti ti-alert-circle"></i></span><h1>Page not found</h1><p class="text-muted">We couldn’t find the page you requested.</p><a class="btn btn-primary" href="<?= h(url()) ?>">Return to the portal</a></div></section>
<?php
endswitch;
