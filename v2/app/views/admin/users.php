<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="dashboard-container">
    <?php require __DIR__ . '/../partials/_admin-sidebar.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>مدیریت کاربران</h1>
                    <p>لیست تمام کاربران سیستم</p>
                </div>
                <a href="/admin/users/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> کاربر جدید
                </a>
            </div>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="content-card mb-4">
            <form method="GET" action="/admin/users" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="جستجو بر اساس نام یا ایمیل..."
                           value="<?= e($search) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> جستجو
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>وضعیت</th>
                            <th>آخرین ورود</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= e($u['fullname']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                    <?= $u['role'] === 'admin' ? 'مدیر' : 'کاربر' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= $u['status'] === 'active' ? 'فعال' : 'مسدود' ?>
                                </span>
                            </td>
                            <td><?= $u['last_login'] ? time_ago($u['last_login']) : 'هرگز' ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-outline-primary" title="ویرایش">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="/admin/users/<?= $u['id'] ?>/toggle-status" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-<?= $u['status'] === 'active' ? 'warning' : 'success' ?>" 
                                                title="<?= $u['status'] === 'active' ? 'مسدود کردن' : 'فعال کردن' ?>">
                                            <i class="bi bi-<?= $u['status'] === 'active' ? 'lock' : 'unlock' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/users/<?= $u['id'] ?>/delete" class="d-inline" 
                                          onsubmit="return confirm('آیا از حذف این کاربر مطمئن هستید?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="mt-2 text-muted">کاربری یافت نشد</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= e($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
