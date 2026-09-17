<?php ob_start(); ?>
<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<div class="home-container">
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>به کلینیک تغذیه دکتر زهرابی خوش آمدید</h1>
                    <p class="lead">برنامه غذایی اختصاصی و مشاوره تغذیه حرفه‌ای برای رسیدن به اهداف سلامتی شما</p>
                    <div class="hero-buttons">
                        <?php if (current_user()): ?>
                        <a href="/dashboard" class="btn btn-primary btn-lg">
                            <i class="bi bi-speedometer2"></i> داشبورد
                        </a>
                        <?php else: ?>
                        <a href="/register" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus"></i> ثبت نام رایگان
                        </a>
                        <a href="/login" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-box-arrow-in-left"></i> ورود
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <h2 class="text-center mb-5">چرا ما را انتخاب کنید؟</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <h3>مشاوره اختصاصی</h3>
                        <p>برنامه غذایی مناسب با شرایط و نیازهای خاص شما</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h3>پیگیری پیشرفت</h3>
                        <p>نظارت مداوم بر پیشرفت و تنظیم برنامه بر اساس نتایج</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3>تضمین کیفیت</h3>
                        <p>تیم متخصص با سال‌ها تجربه در زمینه تغذیه و رژیم درمانی</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.home-container {
    min-height: calc(100vh - 60px);
}

.hero-section {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white;
    padding: 80px 0;
    min-height: 60vh;
    display: flex;
    align-items: center;
}

.hero-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
}

.hero-section .lead {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 30px;
}

.hero-buttons .btn {
    margin-left: 10px;
    margin-bottom: 10px;
}

.hero-image {
    text-align: center;
    font-size: 12rem;
    opacity: 0.3;
}

.features-section {
    padding: 80px 0;
    background: white;
}

.features-section h2 {
    font-weight: 700;
    color: #1e293b;
}

.feature-card {
    text-align: center;
    padding: 30px;
    border-radius: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: rgba(22, 163, 74, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #16a34a;
}

.feature-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.feature-card p {
    color: #64748b;
}
</style>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../layouts/main.php'; ?>
