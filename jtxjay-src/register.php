<?php
require_once __DIR__ . '/includes/functions.php';
$themeColor = getThemeColor();

$error = '';
$success = '';

if(isLoggedIn()) redirect('index.php');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = Database::getInstance();
    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $code = trim($_POST['code'] ?? '');

    if($action == 'send_code') {
        if(!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'message'=>'请输入有效的邮箱地址']);
            exit;
        }
        $exist = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if($exist) {
            echo json_encode(['success'=>false,'message'=>'该邮箱已被注册']);
            exit;
        }
        $genCode = generateCode();
        $expire = date('Y-m-d H:i:s', time() + 600);
        // Clear old codes
        $db->delete('email_codes', 'email = ? AND type = ?', [$email, 'register']);
        $db->insert('email_codes', [
            'email' => $email,
            'code' => $genCode,
            'type' => 'register',
            'expire_at' => $expire
        ]);
        // Send email with nice HTML template
        $subject = 'Jay影视 邮箱验证码';
        $body = '
        <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
            <div style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);padding:40px 30px;text-align:center;color:#fff;">
                <div style="display:inline-flex;align-items:center;gap:10px;font-size:26px;font-weight:800;">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,0.2);border-radius:12px;position:relative;"></div>
                    Jay影视
                </div>
                <div style="margin-top:10px;font-size:14px;opacity:0.9;">邮箱验证码</div>
            </div>
            <div style="padding:40px 30px;">
                <div style="font-size:16px;color:#333;line-height:1.8;margin-bottom:20px;">
                    您好，<strong>' . sanitize($email) . '</strong>，<br>
                    感谢您注册 Jay影视！您的验证码如下：
                </div>
                <div style="text-align:center;padding:24px;background:linear-gradient(135deg,rgba(139,92,246,0.1),rgba(139,92,246,0.05));border-radius:12px;margin-bottom:20px;">
                    <div style="font-size:40px;font-weight:900;color:#8b5cf6;letter-spacing:12px;font-family:Courier,monospace;">' . $genCode . '</div>
                </div>
                <div style="font-size:13px;color:#888;line-height:1.7;">
                    • 验证码有效期 10 分钟<br>
                    • 请勿将验证码告诉任何人<br>
                    • 如非本人操作，请忽略此邮件
                </div>
            </div>
            <div style="padding:20px 30px;background:#fafafa;border-top:1px solid #eee;font-size:12px;color:#aaa;text-align:center;">
                © ' . date('Y') . ' Jay影视 版权所有
            </div>
        </div>';
        $sent = @sendEmail($email, $subject, $body);
        if($sent) {
            echo json_encode(['success'=>true,'message'=>'验证码已发送到您的邮箱，请注意查收']);
        } else {
            echo json_encode(['success'=>false,'message'=>'邮件发送失败，请稍后重试']);
        }
        exit;
    }

    // Register submit
    if(!$email || !$username || !$password || !$password2 || !$code) {
        $error = '请填写完整信息';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif(mb_strlen($username) < 2 || mb_strlen($username) > 20) {
        $error = '用户名长度为 2-20 个字符';
    } elseif(strlen($password) < 6) {
        $error = '密码至少 6 位';
    } elseif($password != $password2) {
        $error = '两次输入的密码不一致';
    } else {
        $exist = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if($exist) { $error = '该邮箱已被注册'; goto render; }
        $codeRow = $db->fetchOne("SELECT * FROM email_codes WHERE email = ? AND type = 'register' ORDER BY id DESC LIMIT 1", [$email]);
        if(!$codeRow || $codeRow['code'] != $code) {
            $error = '验证码错误'; goto render;
        }
        if(strtotime($codeRow['expire_at']) < time()) {
            $error = '验证码已过期，请重新获取'; goto render;
        }
        // Create user
        $db->insert('users', [
            'email' => $email,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $db->delete('email_codes', 'email = ? AND type = ?', [$email, 'register']);
        redirect('login.php?registered=1');
    }
}
render:
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>注册 - <?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
:root {
    --primary: <?php echo $themeColor; ?>;
    --primary-dark: <?php echo adjustColor($themeColor, -15); ?>;
    --primary-light: <?php echo adjustColor($themeColor, 15); ?>;
}
.code-sent-notice {
    display:none;
    padding:12px 16px;
    background:rgba(16,185,129,0.1);
    border:1px solid rgba(16,185,129,0.3);
    color:#10b981;
    border-radius:10px;
    font-size:13px;
    margin-bottom:16px;
    align-items:center;
    gap:8px;
}
.code-sent-notice.show { display:flex; }
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
            <div class="auth-subtitle">创建账号，畅享高清影视资源</div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger" style="display:flex;align-items:flex-start;gap:10px;"><?php echo $error; ?></div>
        <?php endif; ?>
        <div id="codeSentNotice" class="code-sent-notice">
            <span class="icon icon-bell"></span>
            <span id="codeSentText">验证码已发送到您的邮箱，10分钟内有效</span>
        </div>

        <form method="POST" id="regForm">
            <div class="form-group">
                <label class="form-label">邮箱地址</label>
                <div class="input-with-suffix">
                    <input type="email" name="email" id="regEmail" class="form-control" placeholder="请输入邮箱" required value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
                    <button type="button" class="input-suffix" id="sendCodeBtn" onclick="sendRegisterCode()">获取验证码</button>
                </div>
                <div class="form-hint">我们会向该邮箱发送6位注册验证码</div>
            </div>
            <div class="form-group">
                <label class="form-label">邮箱验证码</label>
                <input type="text" name="code" id="regCode" class="form-control" placeholder="请输入6位验证码" required maxlength="6" style="letter-spacing:4px;font-weight:700;">
            </div>
            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" name="username" class="form-control" placeholder="2-20个字符" required maxlength="20" value="<?php echo sanitize($_POST['username'] ?? ''); ?>">
            </div>
            <div class="input-row">
                <div class="form-group">
                    <label class="form-label">设置密码</label>
                    <input type="password" name="password" class="form-control" placeholder="至少6位" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">确认密码</label>
                    <input type="password" name="password2" class="form-control" placeholder="再次输入密码" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <span class="icon icon-user"></span>立即注册
            </button>
        </form>

        <div class="auth-footer">
            已有账号？<a href="login.php">立即登录</a>
        </div>
    </div>
</div>
<div id="toastContainer" class="toast-container"></div>
<script src="assets/js/main.js"></script>
<script>
var countdown = 0;
var timer = null;
function sendRegisterCode() {
    var email = document.getElementById('regEmail').value.trim();
    if(!email) { showToast('请输入邮箱', 'error'); return; }
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast('邮箱格式不正确', 'error'); return; }
    if(countdown > 0) return;
    var btn = document.getElementById('sendCodeBtn');
    btn.disabled = true;
    btn.style.pointerEvents = 'none';
    btn.textContent = '发送中...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'register.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.timeout = 15000;
    xhr.ontimeout = function() {
        btn.disabled = false;
        btn.style.pointerEvents = '';
        btn.textContent = '获取验证码';
        showToast('请求超时，请重试', 'error');
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.style.pointerEvents = '';
        btn.textContent = '获取验证码';
        showToast('网络错误，请重试', 'error');
    };
    xhr.onload = function() {
        var res; try { res = JSON.parse(xhr.responseText); } catch(e){ res={success:false,message:'服务器错误'}; }
        if(res.success) {
            showToast(res.message, 'success');
            document.getElementById('codeSentNotice').classList.add('show');
            document.getElementById('codeSentText').textContent = '验证码已发送到 ' + email + '，10分钟内有效';
            // 发送成功：开始60秒倒计时
            countdown = 60;
            timer = setInterval(function() {
                countdown--;
                if(countdown <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.style.pointerEvents = '';
                    btn.textContent = '获取验证码';
                } else {
                    btn.textContent = countdown + 's 后重试';
                }
            }, 1000);
        } else {
            // 发送失败：立即恢复按钮
            btn.disabled = false;
            btn.style.pointerEvents = '';
            btn.textContent = '获取验证码';
            showToast(res.message, 'error');
        }
    };
    xhr.send('action=send_code&email=' + encodeURIComponent(email));
}
</script>
</body>
</html>
<?php
// adjustColor 统一在 includes/functions.php 中定义
?>
