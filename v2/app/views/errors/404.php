<?php ob_start(); ?>
<div class="error-page">
    <div class="error-content">
        <div class="error-icon">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h1>خطای 404</h1>
        <p>صفحه مورد نظر یافت نشد</p>
        <a href="/" class="btn btn-primary">
            <i class="bi bi-house"></i> بازگشت به خانه
        </a>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
