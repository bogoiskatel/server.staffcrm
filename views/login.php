<section class="login-card">
 <div class="login-symbol">S</div><h1><?=e(t('sign_in'))?></h1>
 <p class="muted"><?=e(t('welcome'))?></p>
 <?php if($error): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif; ?>
 <form method="post" action="<?=e(routeUrl('login'))?>">
  <input type="hidden" name="csrf" value="<?=e($auth->csrf())?>">
  <label for="username"><?=e(t('username'))?></label><input class="form-control" id="username" name="username" autocomplete="username" maxlength="200" required autofocus>
  <label for="password"><?=e(t('password'))?></label><input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
  <button class="btn btn-primary btn-block login-submit"><?=e(t('login'))?></button>
 </form>
</section>
