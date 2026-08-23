<?php
require_once __DIR__ . '/includes/functions.php';
$themeColor = getThemeColor();

$error = '';
$success = '';

if(isLoggedIn()) {
    redirect('index.php');
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = Database::getInstance();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(!$email || !$password) {
        $error = '请填写完整信息';
    } else {
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        if(!$user || !password_verify($password, $user['password'])) {
            $error = '邮箱或密码错误';
        } else {
            // Check ban
            if($user['banned'] == 1) {
                if($user['ban_end'] && strtotime($user['ban_end']) < time()) {
                    $db->update('users', ['banned'=>0, 'ban_reason'=>'', 'ban_start'=>null, 'ban_end'=>null], 'id=?', [$user['id']]);
                } else {
                    $info = banInfo();
                    $end = $info['end'] ? date('Y-m-d H:i', strtotime($info['end'])) : '永久';
                    $error = '账号已被封禁！<br>原因：' . sanitize($info['reason']) . '<br>解除时间：' . $end;
                    goto render;
                }
            }
            $_SESSION['user_id'] = $user['id'];
            $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirect);
        }
    }
}
render:
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 - <?php echo SITE_NAME; ?></title>
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
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php" class="auth-logo">
                <div class="logo-icon" style="width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg, var(--primary), var(--primary-dark));position:relative;box-shadow:0 4px 12px rgba(139,92,246,0.3);">
                </div>
                <span>Jay影视</span>
            </a>
            <div class="auth-subtitle">欢迎回来，请登录您的账号</div>
        </div>

        <?php if(isset($_GET['need_login']) && $_GET['need_login']): ?>
            <div class="alert alert-info" style="display:flex;align-items:flex-start;gap:10px;">
                <span class="icon icon-bell"></span>
                <span><strong>需要登录才可以观看哦，如没有账号请注册！</strong></span>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['banned']) && $_GET['banned']): ?>
            <div class="alert alert-danger" style="display:flex;align-items:flex-start;gap:10px;">
                <span class="icon icon-close"></span>
                <span>您的账号已被封禁，请联系管理员！</span>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['registered']) && $_GET['registered']): ?>
            <div class="alert alert-success" style="display:flex;align-items:flex-start;gap:10px;">
                <span class="icon icon-star"></span>
                <span>注册成功！请登录您的账号</span>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger" style="display:flex;align-items:flex-start;gap:10px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">邮箱地址</label>
                <input type="email" name="email" class="form-control" placeholder="请输入邮箱" required value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <span class="icon icon-user"></span>立即登录
            </button>
        </form>

        <div class="auth-footer">
            还没有账号？<a href="register.php">立即注册</a>
        </div>
    </div>
</div>
<div id="toastContainer" class="toast-container"></div>
<script src="assets/js/main.js"></script>
</body>
</html>
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
