<?php
require_once __DIR__ . '/../includes/functions.php';
if(!isAdmin()) { redirect('login.php'); }
$db = Database::getInstance();
$themeColor = getThemeColor();
$pageTitle = $pageTitle ?? '管理后台';
$activeMenu = $activeMenu ?? 'dashboard';

// Quick stats
$totalUsers = $db->fetchOne("SELECT COUNT(*) as c FROM users")['c'];
$totalFav = $db->fetchOne("SELECT COUNT(*) as c FROM favorites")['c'];
$totalHist = $db->fetchOne("SELECT COUNT(*) as c FROM watch_history")['c'];
$totalFb = $db->fetchOne("SELECT COUNT(*) as c FROM feedbacks")['c'];
$totalBanned = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE banned = 1")['c'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?> 管理后台</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
:root {
    --primary: <?php echo $themeColor; ?>;
    --primary-dark: <?php echo adjustColor($themeColor, -15); ?>;
    --primary-light: <?php echo adjustColor($themeColor, 15); ?>;
}
</style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <div class="logo-icon" style="width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg, var(--primary), var(--primary-dark));position:relative;box-shadow:0 4px 12px rgba(139,92,246,0.3);"></div>
            <span class="admin-logo-text">Jay影视</span>
        </div>
        <div class="admin-menu">
            <div class="admin-menu-title">概览</div>
            <a href="index.php" class="admin-menu-item <?php echo $activeMenu=='dashboard'?'active':''; ?>">
                <span class="icon icon-home"></span>仪表盘
            </a>

            <div class="admin-menu-title">用户管理</div>
            <a href="users.php" class="admin-menu-item <?php echo $activeMenu=='users'?'active':''; ?>">
                <span class="icon icon-user"></span>用户列表
            </a>
            <a href="history.php" class="admin-menu-item <?php echo $activeMenu=='history'?'active':''; ?>">
                <span class="icon icon-clock"></span>观看历史
            </a>
            <a href="favorites.php" class="admin-menu-item <?php echo $activeMenu=='favorites'?'active':''; ?>">
                <span class="icon icon-heart"></span>用户收藏
            </a>

            <div class="admin-menu-title">内容管理</div>
            <a href="feedbacks.php" class="admin-menu-item <?php echo $activeMenu=='feedbacks'?'active':''; ?>">
                <span class="icon icon-edit"></span>反馈管理
            </a>
            <a href="sources.php" class="admin-menu-item <?php echo $activeMenu=='sources'?'active':''; ?>">
                <span class="icon icon-play"></span>播放源管理
            </a>
            <a href="announcements.php" class="admin-menu-item <?php echo $activeMenu=='announcements'?'active':''; ?>">
                <span class="icon icon-bell"></span>公告/邮件/主题
            </a>

            <div class="admin-menu-title">其他</div>
            <a href="../index.php" class="admin-menu-item">
                <span class="icon icon-chevron icon-chevron-left"></span>返回前台
            </a>
            <a href="logout.php" class="admin-menu-item">
                <span class="icon icon-logout"></span>退出后台
            </a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div class="admin-header-title"><?php echo $pageTitle; ?></div>
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;">
                        杰
                    </div>
                    <div>
                        <div style="font-weight:700;display:flex;align-items:center;gap:8px;">
                            杰同学
                            <span class="admin-badge">开发者</span>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">超级管理员</div>
                    </div>
                </div>
            </div>
        </header>
        <div class="admin-content">
<?php
function adjustColor($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) != 6) $hex = '8b5cf6';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + ($percent * 2.55)));
    $g = max(0, min(255, $g + ($percent * 2.55)));
    $b = max(0, min(255, $b + ($percent * 2.55)));
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}
?>
