<section class="login-card modal-content rounded-0 border-top-crm">
 <div class="modal-header bg-light"><h1 class="modal-title"><img src="/assets/staff-crm/branding/crm.svg" alt="STAFF" width="36" height="36" class="mr-2"><?=e(t('sign_in'))?></h1></div>
 <div class="modal-body"><p class="text-muted"><?=e(t('welcome'))?></p>
 <?php if($error): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif; ?>
 <form method="post" action="<?=e(routeUrl('login'))?>">
  <input type="hidden" name="csrf" value="<?=e($auth->csrf())?>">
  <label for="username"><?=e(t('username'))?></label><input class="form-control" id="username" name="username" autocomplete="username" maxlength="200" required autofocus>
  <label for="password"><?=e(t('password'))?></label><input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
  <button class="btn btn-warning btn-block login-submit form-login-button"><?=e(t('login'))?></button>
 </form></div>
</section>
