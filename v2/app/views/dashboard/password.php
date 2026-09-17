<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>تغییر رمز عبور</h1>
            <p>رمز عبور خود را به روز کنید</p>
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

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="content-card">
                    <h4><i class="bi bi-shield-lock"></i> تغییر رمز عبور</h4>
                    
                    <form method="POST" action="/dashboard/password" class="profile-form">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">رمز عبور فعلی</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">رمز عبور جدید</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   required minlength="8">
                            <small class="text-muted">حداقل 8 کاراکتر</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">تکرار رمز عبور جدید</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> تغییر رمز عبور
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
