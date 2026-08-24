<?php
require_once __DIR__ . '/db.php';

function sendEmail($to, $subject, $body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    
    $socket = fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if(!$socket) {
        return false;
    }
    
    $response = fgets($socket, 515);
    smtpSend($socket, "EHLO " . SMTP_HOST);
    smtpSend($socket, "AUTH LOGIN");
    smtpSend($socket, base64_encode(SMTP_USER));
    smtpSend($socket, base64_encode(SMTP_PASS));
    smtpSend($socket, "MAIL FROM: <" . SMTP_FROM . ">");
    smtpSend($socket, "RCPT TO: <" . $to . ">");
    smtpSend($socket, "DATA");
    
    $message = $headers . "\r\n" . $body . "\r\n.\r\n";
    fwrite($socket, $message);
    $response = fgets($socket, 515);
    
    smtpSend($socket, "QUIT");
    fclose($socket);
    
    return true;
}

function smtpSend($socket, $command) {
    fwrite($socket, $command . "\r\n");
    return fgets($socket, 515);
}

function generateCode($length = 6) {
    $chars = '0123456789';
    $code = '';
    for($i = 0; $i < $length; $i++) {
        $code .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if(!isLoggedIn()) return null;
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function isBanned() {
    $user = currentUser();
    if(!$user) return false;
    if($user['banned'] == 1) {
        if($user['ban_end'] && strtotime($user['ban_end']) < time()) {
            $db = Database::getInstance();
            $db->update('users', ['banned' => 0, 'ban_reason' => '', 'ban_start' => null, 'ban_end' => null], 'id = ?', [$user['id']]);
            $_SESSION['banned'] = 0;
            return false;
        }
        return true;
    }
    return false;
}

function banInfo() {
    $user = currentUser();
    if(!$user) return null;
    return [
        'reason' => $user['ban_reason'],
        'start' => $user['ban_start'],
        'end' => $user['ban_end']
    ];
}

function requireLogin() {
    if(!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php?need_login=1');
        exit;
    }
    if(isBanned()) {
        session_destroy();
        header('Location: login.php?banned=1');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if(!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function getSetting($key) {
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : null;
}

function setSetting($key, $value) {
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
    if($existing) {
        $db->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
    } else {
        $db->insert('site_settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

function getThemeColor() {
    $color = getSetting('theme_primary');
    return $color ? $color : '#8b5cf6';
}

function getDefaultPlaySource() {
    $db = Database::getInstance();
    $src = $db->fetchOne("SELECT * FROM play_sources WHERE is_default = 1 LIMIT 1");
    if(!$src) {
        $src = $db->fetchOne("SELECT * FROM play_sources LIMIT 1");
    }
    return $src;
}

function getAllPlaySources() {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM play_sources ORDER BY is_default DESC, id ASC");
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if($diff < 60) return '刚刚';
    if($diff < 3600) return floor($diff / 60) . '分钟前';
    if($diff < 86400) return floor($diff / 3600) . '小时前';
    if($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d', $time);
}

function formatDuration($seconds) {
    if($seconds < 60) return $seconds . '秒';
    if($seconds < 3600) return floor($seconds / 60) . '分' . ($seconds % 60) . '秒';
    return floor($seconds / 3600) . '时' . floor(($seconds % 3600) / 60) . '分';
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function csrfToken() {
    if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** 调整十六进制颜色亮度（正数变亮，负数变暗） */
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
