<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dr. Zohrabi Nutrition Clinic' ?></title>
    
    <!-- Vazirmatn Font -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?= $content ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>
    
    <!-- Flash Messages -->
    <?php if (!empty($success)): ?>
    <script>
        showToast('success', '<?= addslashes($success) ?>');
    </script>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <script>
        showToast('error', '<?= addslashes($error) ?>');
    </script>
    <?php endif; ?>
</body>
</html>
