<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>پروفایل</h1>
            <p>اطلاعات شخصی خود را مدیریت کنید</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

        <div class="row">
            <!-- Avatar Section -->
            <div class="col-md-4">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php if ($profile && $profile['avatar']): ?>
                        <img src="<?= e($profile['avatar']) ?>" alt="آواتار" class="avatar-img">
                        <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="bi bi-person"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3><?= e($user['fullname']) ?></h3>
                    <p class="text-muted"><?= e($user['email']) ?></p>
                    
                    <form method="POST" action="/dashboard/avatar" enctype="multipart/form-data" class="mt-3">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-camera"></i> تغییر آواتار
                        </button>
                    </form>
                </div>
            </div>

            <!-- Profile Form -->
            <div class="col-md-8">
                <div class="content-card">
                    <h4>ویرایش پروفایل</h4>
                    
                    <form method="POST" action="/dashboard/profile" class="profile-form">
                        <?= csrf_field() ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="fullname" class="form-label">نام کامل</label>
                                <input type="text" id="fullname" name="fullname" class="form-control" 
                                       value="<?= e($old['fullname'] ?? $user['fullname']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= e($old['email'] ?? $user['email']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label">تلفن</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?= e($old['phone'] ?? ($profile['phone'] ?? '')) ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="form-label">آدرس</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?= e($old['address'] ?? ($profile['address'] ?? '')) ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> ذخیره تغییرات
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
