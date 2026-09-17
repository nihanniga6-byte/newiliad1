<?php ob_start(); ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-key"></i>
            </div>
            <h1>بازیابی رمز عبور</h1>
            <p>ایمیل خود را وارد کنید تا لینک بازیابی رمز عبور برایتان ارسال شود</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/forgot-password" class="auth-form">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="email">ایمیل</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="example@email.com" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="bi bi-send"></i>
                ارسال لینک بازیابی
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="/login" class="auth-link">بازگشت به ورود</a></p>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
