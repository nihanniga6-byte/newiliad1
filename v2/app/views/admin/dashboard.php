<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_admin-sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <h1>داشبورد مدیریت</h1>
            <p>خوش آمدید، <?= e($user['fullname']) ?>!</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-primary">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_users'] ?></h3>
                        <p>کل کاربران</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-success">
                    <div class="stat-icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['active_users'] ?></h3>
                        <p>کاربران فعال</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-info">
                    <div class="stat-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['today_users'] ?></h3>
                        <p>ثبت نام امروز</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-warning">
                    <div class="stat-icon">
                        <i class="bi bi-display"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $active_sessions ?></h3>
                        <p>نشست‌های فعال</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>آخرین کاربران</h4>
                        <a href="/admin/users" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>نام</th>
                                    <th>ایمیل</th>
                                    <th>نقش</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $u): ?>
                                <tr>
                                    <td><?= e($u['fullname']) ?></td>
                                    <td><?= e($u['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                            <?= $u['role'] === 'admin' ? 'مدیر' : 'کاربر' ?>
                                        </span>
                                    </td>
                                    <td><?= time_ago($u['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h4>آخرین فعالیت‌ها</h4>
                    
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="bi bi-circle-fill"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?= e($activity['fullname'] ?? 'سیستم') ?></strong>: <?= e($activity['action']) ?></p>
                                <small class="text-muted"><?= time_ago($activity['created_at']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
