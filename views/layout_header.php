<?php
/** @var array $config */
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Http::esc($pageTitle) ?> — Call Analyzer</title>
    <link rel="stylesheet" href="<?= Http::esc(Http::url('/assets/css/app.css', $config)) ?>">
</head>
<body>
<header class="top">
    <div class="wrap top-inner">
        <a class="brand" href="<?= Http::esc(Http::url('/', $config)) ?>">Call Analyzer</a>
        <nav class="nav">
            <a href="<?= Http::esc(Http::url('/?action=dashboard', $config)) ?>">Dashboard</a>
            <a href="<?= Http::esc(Http::url('/?action=upload', $config)) ?>">Upload</a>
            <a href="<?= Http::esc(Http::url('/?action=help', $config)) ?>">How it works</a>
        </nav>
    </div>
</header>
<main class="wrap main">
<?php
if (!empty($_SESSION['flash'])) {
    echo '<div class="flash">' . Http::esc((string) $_SESSION['flash']) . '</div>';
    unset($_SESSION['flash']);
}
?>
