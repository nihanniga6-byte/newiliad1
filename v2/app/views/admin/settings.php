<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_admin-sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>تنظیمات سایت</h1>
            <p>مدیریت تنظیمات عمومی سایت</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="content-card">
                    <h4>اطلاعات سایت</h4>
                    
                    <form method="POST" action="/admin/settings/update" class="profile-form">
                        <?= csrf_field() ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="site_name" class="form-label">نام سایت</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?= e($settings['site_name'] ?? 'Dr. Zohrabi Nutrition Clinic') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="site_email" class="form-label">ایمیل سایت</label>
                                <input type="email" id="site_email" name="site_email" class="form-control" 
                                       value="<?= e($settings['site_email'] ?? 'info@zohrabi.com') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="site_phone" class="form-label">تلفن</label>
                                <input type="tel" id="site_phone" name="site_phone" class="form-control" 
                                       value="<?= e($settings['site_phone'] ?? '') ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="site_address" class="form-label">آدرس</label>
                                <textarea id="site_address" name="site_address" class="form-control" rows="2"><?= e($settings['site_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="checkbox" id="maintenance_mode" name="maintenance_mode" class="form-check-input"
                                           <?= ($settings['maintenance_mode'] ?? false) ? 'checked' : '' ?>>
                                    <label for="maintenance_mode" class="form-check-label">حالت تعمیر و نگهداری</label>
                                </div>
                                <small class="text-muted">فعال کردن این گزینه سایت را برای کاربران عادی غیرفعال می‌کند</small>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> ذخیره تنظیمات
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card">
                    <h4>عملیات</h4>
                    
                    <div class="d-grid gap-2">
                        <form method="POST" action="/admin/settings/clear-cache">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-trash"></i> پاک کردن کش
                            </button>
                        </form>
                        
                        <a href="/cron.php?token=<?= defined('CRON_SECRET') ? CRON_SECRET : '' ?>" 
                           class="btn btn-info" target="_blank">
                            <i class="bi bi-clock-history"></i> اجرای cron
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
