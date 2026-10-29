<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · FCWM Student Support</title>
  <link rel="icon" href="<?= h(asset('dist/assets/images/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(asset('dist/assets/fonts/phosphor/duotone/style.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('dist/assets/fonts/tabler-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('dist/assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset('assets/css/app.css')) ?>">
</head>
<body>
<?php if ($role !== 'public'): ?>
<nav class="pc-sidebar">
  <div class="navbar-wrapper">
    <div class="m-header">
      <a href="<?= h(url('page=dashboard')) ?>" class="b-brand text-primary brand-lockup">
        <span class="brand-mark">F</span><span class="brand-text"><b>FCWM</b><small>Student Support</small></span>
      </a>
    </div>
    <div class="navbar-content">
      <ul class="pc-navbar">
        <li class="pc-item pc-caption"><label><?= h(ucfirst($role)) ?> workspace</label><i class="ti ti-layout-dashboard"></i></li>
        <li class="pc-item <?= $page === 'dashboard' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=dashboard')) ?>"><span class="pc-micon"><i class="ti ti-layout-dashboard"></i></span><span class="pc-mtext">Overview</span></a></li>
        <?php if ($role === 'student'): ?>
        <li class="pc-item <?= $page === 'new-ticket' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=new-ticket')) ?>"><span class="pc-micon"><i class="ti ti-circle-plus"></i></span><span class="pc-mtext">Create a complaint</span></a></li>
        <li class="pc-item <?= $page === 'my-tickets' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=my-tickets')) ?>"><span class="pc-micon"><i class="ti ti-inbox"></i></span><span class="pc-mtext">My complaints</span></a></li>
        <?php elseif ($role === 'admin'): ?>
        <li class="pc-item <?= $page === 'inbox' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=inbox')) ?>"><span class="pc-micon"><i class="ti ti-route"></i></span><span class="pc-mtext">Route &amp; manage</span></a></li>
        <li class="pc-item <?= $page === 'staff' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=staff')) ?>"><span class="pc-micon"><i class="ti ti-users"></i></span><span class="pc-mtext">Department accounts</span></a></li>
        <li class="pc-item <?= $page === 'courses' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=courses')) ?>"><span class="pc-micon"><i class="ti ti-books"></i></span><span class="pc-mtext">Course directory</span></a></li>
        <li class="pc-item <?= $page === 'organization' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=organization')) ?>"><span class="pc-micon"><i class="ti ti-building-community"></i></span><span class="pc-mtext">Schools &amp; departments</span></a></li>
        <?php else: ?>
        <li class="pc-item <?= $page === 'department-inbox' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=department-inbox')) ?>"><span class="pc-micon"><i class="ti ti-inbox"></i></span><span class="pc-mtext">Department cases</span></a></li>
        <li class="pc-item <?= $page === 'courses' ? 'active' : '' ?>"><a class="pc-link" href="<?= h(url('page=courses')) ?>"><span class="pc-micon"><i class="ti ti-books"></i></span><span class="pc-mtext">Course directory</span></a></li>
        <?php endif; ?>
      </ul>
      <div class="sidebar-support-card">
        <span class="support-icon"><i class="ti ti-leaf"></i></span>
        <h6>Here to help</h6>
        <p>Every complaint is tracked until it gets a response.</p>
        <a href="<?= h(url('page=track')) ?>">Track a ticket <i class="ti ti-arrow-up-right"></i></a>
      </div>
      <div class="sidebar-campus">Federal College of Wildlife Management<br>New Bussa, Niger State</div>
    </div>
  </div>
</nav>
<header class="pc-header">
  <div class="header-wrapper">
    <div class="me-auto pc-mob-drp">
      <ul class="list-unstyled mb-0"><li class="pc-h-item pc-sidebar-popup"><a class="pc-head-link head-link-secondary ms-0" href="#" id="mobile-collapse"><i class="ti ti-menu-2"></i></a></li></ul>
    </div>
    <div class="header-user">
      <div class="user-avatar"><?= h(strtoupper(substr((string) $current['fullname'], 0, 1))) ?></div>
      <div class="header-user-copy"><b><?= h($current['fullname']) ?></b><span><?= h($role === 'department' ? 'Department staff' : ucfirst($role)) ?></span></div>
      <form method="post" class="ms-2"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn btn-sm btn-light" type="submit" aria-label="Sign out"><i class="ti ti-logout"></i></button></form>
    </div>
  </div>
</header>
<div class="pc-container">
  <main class="pc-content">
<?php else: ?>
<header class="public-header">
  <a href="<?= h(url()) ?>" class="b-brand text-primary brand-lockup"><span class="brand-mark">F</span><span class="brand-text"><b>FCWM</b><small>Student Support</small></span></a>
  <nav class="public-nav">
    <a href="<?= h(url('page=track')) ?>">Track a complaint</a>
    <?php if ($current === null): ?><a href="<?= h(url('page=login')) ?>">Sign in</a><a href="<?= h(url('page=register')) ?>" class="btn btn-primary btn-sm">Student registration</a><?php endif; ?>
  </nav>
</header>
<main class="public-main">
<?php endif; ?>
<?php if ($flashMessage): ?>
  <div class="alert alert-<?= h($flashMessage['type']) ?> alert-dismissible fade show app-alert" role="alert">
    <?= h($flashMessage['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
