<?php ob_start(); ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <h1>ورود به حساب کاربری</h1>
            <p>خوش آمدید! لطفاً اطلاعات خود را وارد کنید.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="email">ایمیل</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="example@email.com" required
                           value="<?= e($old['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">رمز عبور</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="رمز عبور خود را وارد کنید" required>
                </div>
            </div>

            <div class="form-group row align-items-center">
                <div class="col-6">
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember" class="form-check-input">
                        <label for="remember" class="form-check-label">مرا به خاطر بسپار</label>
                    </div>
                </div>
                <div class="col-6 text-start">
                    <a href="/forgot-password" class="auth-link">رمز عبور را فراموش کردید؟</a>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="bi bi-box-arrow-in-left"></i>
                ورود
            </button>
        </form>

        <div class="auth-footer">
            <p>حساب کاربری ندارید؟ <a href="/register" class="auth-link">ثبت نام کنید</a></p>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
