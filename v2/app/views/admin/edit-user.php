<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_admin-sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>ویرایش کاربر</h1>
            <p>ویرایش اطلاعات کاربر <?= e($targetUser['fullname']) ?></p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

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

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="content-card">
                    <form method="POST" action="/admin/users/<?= $targetUser['id'] ?>/update" class="profile-form">
                        <?= csrf_field() ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="fullname" class="form-label">نام کامل</label>
                                <input type="text" id="fullname" name="fullname" class="form-control" 
                                       value="<?= e($old['fullname'] ?? $targetUser['fullname']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= e($old['email'] ?? $targetUser['email']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="role" class="form-label">نقش</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="user" <?= ($old['role'] ?? $targetUser['role']) === 'user' ? 'selected' : '' ?>>کاربر</option>
                                    <option value="admin" <?= ($old['role'] ?? $targetUser['role']) === 'admin' ? 'selected' : '' ?>>مدیر</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label">وضعیت</label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="active" <?= ($old['status'] ?? $targetUser['status']) === 'active' ? 'selected' : '' ?>>فعال</option>
                                    <option value="banned" <?= ($old['status'] ?? $targetUser['status']) === 'banned' ? 'selected' : '' ?>>مسدود</option>
                                </select>
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
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> ذخیره تغییرات
                                </button>
                                <a href="/admin/users" class="btn btn-secondary">
                                    <i class="bi bi-x-lg"></i> انصراف
                                </a>
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
