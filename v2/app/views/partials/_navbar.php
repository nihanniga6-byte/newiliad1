<?php
$user = current_user();
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Dr. Zohrabi Nutrition Clinic';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">
            <i class="bi bi-heart-pulse"></i>
            <?= e($siteName) ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/">خانه</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if ($user): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= e($user['fullname']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-speedometer2"></i> داشبورد</a></li>
                        <?php if ($user['role'] === 'admin'): ?>
                        <li><a class="dropdown-item" href="/admin/dashboard"><i class="bi bi-gear"></i> مدیریت</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-left"></i> خروج
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/login">ورود</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/register">ثبت نام</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
