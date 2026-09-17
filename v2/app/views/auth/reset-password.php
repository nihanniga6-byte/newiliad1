<?php ob_start(); ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h1>تغییر رمز عبور</h1>
            <p>رمز عبور جدید خود را وارد کنید</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/reset-password/<?= e($token) ?>" class="auth-form">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="password">رمز عبور جدید</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="حداقل 8 کاراکتر" required minlength="8">
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">تکرار رمز عبور</label>
                <div class="input-icon">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="رمز عبور را مجدداً وارد کنید" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="bi bi-check-lg"></i>
                تغییر رمز عبور
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="/login" class="auth-link">بازگشت به ورود</a></p>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
