<?php
require_once __DIR__ . '/../includes/functions.php';

if(isAdmin()) { redirect('index.php'); }

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = ADMIN_USER;
        redirect('index.php');
    } else {
        $error = '账号或密码错误';
    }
}
$themeColor = getThemeColor();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理员登录 - <?php echo SITE_NAME; ?></title>
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
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:420px;">
        <div class="auth-header">
            <div style="display:inline-flex;align-items:center;gap:12px;">
                <div class="logo-icon" style="width:46px;height:46px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#ef4444,#dc2626);position:relative;box-shadow:0 6px 18px rgba(239,68,68,0.3);"></div>
                <div>
                    <div style="font-size:26px;font-weight:800;background:linear-gradient(135deg,var(--primary),#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Jay影视</div>
                    <div style="font-size:14px;color:var(--text-muted);margin-top:2px;">管理后台登录</div>
                </div>
            </div>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger" style="display:flex;align-items:flex-start;gap:10px;">
            <span class="icon icon-close"></span>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">管理员账号</label>
                <input type="text" name="username" class="form-control" placeholder="请输入管理员账号" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">管理员密码</label>
                <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <span class="icon icon-settings"></span>登录后台
            </button>
        </form>
        <div class="auth-footer">
            <a href="../index.php">← 返回网站首页</a>
        </div>
    </div>
</div>
<div id="toastContainer" class="toast-container"></div>
</body>
</html>
<?php
// adjustColor() 统一在 includes/functions.php 中定义，避免重复声明
?>
