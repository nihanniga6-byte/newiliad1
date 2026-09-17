<aside class="sidebar sidebar-admin">
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/dashboard') !== false ? 'active' : '' ?>" 
                   href="/admin/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    داشبورد
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/users') !== false ? 'active' : '' ?>" 
                   href="/admin/users">
                    <i class="bi bi-people"></i>
                    کاربران
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/settings') !== false ? 'active' : '' ?>" 
                   href="/admin/settings">
                    <i class="bi bi-gear"></i>
                    تنظیمات
                </a>
            </li>
        </ul>
    </nav>
</aside>
