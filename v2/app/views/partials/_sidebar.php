<aside class="sidebar">
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') === 0 && strpos($_SERVER['REQUEST_URI'], '/admin') === false ? 'active' : '' ?>" 
                   href="/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    داشبورد
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard/profile') !== false ? 'active' : '' ?>" 
                   href="/dashboard/profile">
                    <i class="bi bi-person"></i>
                    پروفایل
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard/password') !== false ? 'active' : '' ?>" 
                   href="/dashboard/password">
                    <i class="bi bi-key"></i>
                    تغییر رمز عبور
                </a>
            </li>
        </ul>
    </nav>
</aside>
