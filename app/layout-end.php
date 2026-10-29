<?php if ($role !== 'public'): ?>
  </main>
</div>
<footer class="pc-footer"><div class="footer-wrapper container-fluid"><div class="row"><div class="col-sm-6 my-1"><p class="m-0">Federal College of Wildlife Management · New Bussa <span class="footer-role">/ <?= h(match ($role) { 'admin' => 'Administrator Console', 'department' => 'Department Support Desk', default => 'Student Portal' }) ?></span></p></div><div class="col-sm-6 my-1 text-sm-end"><a href="<?= h(url('page=track')) ?>">Track a complaint</a></div></div></div></footer>
<?php else: ?>
</main>
<footer class="public-footer"><span>Federal College of Wildlife Management · New Bussa, Niger State</span><span>Student Support &amp; Complaint Portal</span></footer>
<?php endif; ?>
<script src="<?= h(asset('dist/assets/js/plugins/popper.min.js')) ?>"></script>
<script src="<?= h(asset('dist/assets/js/plugins/bootstrap.min.js')) ?>"></script>
<script src="<?= h(asset('dist/assets/js/plugins/simplebar.min.js')) ?>"></script>
<script src="<?= h(asset('dist/assets/js/script.js')) ?>"></script>
<script src="<?= h(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
