<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>داشبورد</h1>
            <p>خوش آمدید، <?= e($user['fullname']) ?>!</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Card -->
            <div class="col-md-6 col-lg-4">
                <div class="dashboard-card">
                    <div class="card-icon bg-primary">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="card-content">
                        <h3>پروفایل</h3>
                        <p>اطلاعات شخصی خود را مشاهده و ویرایش کنید</p>
                        <a href="/dashboard/profile" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> ویرایش پروفایل
                        </a>
                    </div>
                </div>
            </div>

            <!-- Password Card -->
            <div class="col-md-6 col-lg-4">
                <div class="dashboard-card">
                    <div class="card-icon bg-warning">
                        <i class="bi bi-key"></i>
                    </div>
                    <div class="card-content">
                        <h3>تغییر رمز عبور</h3>
                        <p>رمز عبور خود را به روز کنید</p>
                        <a href="/dashboard/password" class="btn btn-outline-warning">
                            <i class="bi bi-shield-lock"></i> تغییر رمز
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="col-md-6 col-lg-4">
                <div class="dashboard-card">
                    <div class="card-icon bg-info">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3>اطلاعات حساب</h3>
                        <ul class="account-info">
                            <li><strong>نام:</strong> <?= e($user['fullname']) ?></li>
                            <li><strong>ایمیل:</strong> <?= e($user['email']) ?></li>
                            <li><strong>نقش:</strong> <?= $user['role'] === 'admin' ? 'مدیر' : 'کاربر' ?></li>
                            <li><strong>تاریخ عضویت:</strong> <?= format_date($user['created_at']) ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
