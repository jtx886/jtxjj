<?php
require_once __DIR__ . '/functions.php';
$themeColor = getThemeColor();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = currentUser();
$activeNav = $currentPage;
if($activeNav == 'category' && isset($_GET['type'])) $activeNav = $_GET['type'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="<?php echo $themeColor; ?>">
<title><?php echo SITE_NAME; ?> - 在线观看高清影视</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
:root {
    --primary: <?php echo $themeColor; ?>;
    --primary-dark: <?php echo adjustColor($themeColor, -15); ?>;
    --primary-light: <?php echo adjustColor($themeColor, 15); ?>;
}
</style>
</head>
<body>
<header class="header">
    <div class="container header-inner">
        <a href="index.php" class="logo">
            <div class="logo-icon"></div>
            <span>Jay影视</span>
        </a>
        <nav class="nav">
            <a href="index.php" class="nav-link <?php echo $activeNav=='index'?'active':''; ?>">首页</a>
            <a href="category.php?type=movie" class="nav-link <?php echo $activeNav=='movie'?'active':''; ?>">电影</a>
            <a href="category.php?type=tv" class="nav-link <?php echo $activeNav=='tv'?'active':''; ?>">电视剧</a>
            <a href="category.php?type=anime" class="nav-link <?php echo $activeNav=='anime'?'active':''; ?>">动漫</a>
            <a href="category.php?type=variety" class="nav-link <?php echo $activeNav=='variety'?'active':''; ?>">综艺</a>
            <a href="feedback.php" class="nav-link <?php echo $activeNav=='feedback'?'active':''; ?>">反馈</a>
        </nav>
        <div class="header-actions">
            <form method="GET" action="search.php" class="search-box">
                <span class="search-icon"></span>
                <input type="text" name="q" class="search-input" placeholder="搜索电影、电视剧、动漫..." required>
            </form>
            <?php if($user): ?>
                <div class="dropdown" id="userDropdown">
                    <div class="user-avatar" onclick="toggleDropdown('userDropdown')">
                        <?php if($user['avatar']): ?>
                            <img src="<?php echo sanitize($user['avatar']); ?>" alt="avatar">
                        <?php else: ?>
                            <?php echo mb_substr($user['username'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-item" style="cursor:default">
                            <span class="user-avatar user-avatar-sm">
                                <?php if($user['avatar']): ?>
                                    <img src="<?php echo sanitize($user['avatar']); ?>" alt="">
                                <?php else: ?>
                                    <?php echo mb_substr($user['username'], 0, 1); ?>
                                <?php endif; ?>
                            </span>
                            <div>
                                <div style="font-weight:600;color:#fff"><?php echo sanitize($user['username']); ?></div>
                                <div style="font-size:12px;color:var(--text-muted)"><?php echo sanitize($user['email']); ?></div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item"><span class="icon icon-user"></span>我的主页</a>
                        <a href="profile.php?tab=favorites" class="dropdown-item"><span class="icon icon-heart"></span>我的收藏</a>
                        <a href="profile.php?tab=history" class="dropdown-item"><span class="icon icon-clock"></span>观看历史</a>
                        <a href="admin/login.php" class="dropdown-item"><span class="icon icon-settings"></span>管理后台</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item" style="color:#ef4444"><span class="icon icon-logout"></span>退出登录</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-secondary btn-sm">登录</a>
                <a href="register.php" class="btn btn-primary btn-sm">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
<?php
// adjustColor() 统一在 includes/functions.php 中定义，避免重复声明
?>
