<?php ob_start(); ?>
<div class="error-page">
    <div class="error-content">
        <div class="error-icon error-icon-500">
            <i class="bi bi-bug"></i>
        </div>
        <h1>خطای 500</h1>
        <p>مشکلی در سرور رخ داده است. لطفاً بعداً تلاش کنید.</p>
        <a href="/" class="btn btn-primary">
            <i class="bi bi-house"></i> بازگشت به خانه
        </a>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
