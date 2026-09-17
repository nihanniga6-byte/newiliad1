<?php ob_start(); ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-person-plus"></i>
            </div>
            <h1>ثبت نام</h1>
            <p>حساب کاربری جدید ایجاد کنید</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="/register" class="auth-form">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="fullname">نام کامل</label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input type="text" id="fullname" name="fullname" class="form-control" 
                           placeholder="نام و نام خانوادگی" required
                           value="<?= e($old['fullname'] ?? '') ?>">
                </div>
            </div>

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
                <i class="bi bi-person-plus"></i>
                ثبت نام
            </button>
        </form>

        <div class="auth-footer">
            <p>قبلاً ثبت نام کرده‌اید؟ <a href="/login" class="auth-link">ورود</a></p>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
