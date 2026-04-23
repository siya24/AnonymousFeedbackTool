<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Anonymous Feedback Tool', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<header class="topbar">
    <h1>Voice Without Fear</h1>
    <nav>
        <a href="/">Home</a>
        <a href="/hr">HR Console</a>
    </nav>
</header>
<main class="container">
    <?php require $viewPath; ?>
</main>
<script src="/public/assets/js/app.js" defer></script>
</body>
</html>
