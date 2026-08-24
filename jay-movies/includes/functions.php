<?php
require_once __DIR__ . '/db.php';

/**
 * 安全发送邮件：严格超时（默认连 5 秒 / 总 12 秒）
 * 如果 SMTP 连不上或任何一步卡住，立即返回 false 而不是把页面挂死。
 */
function sendEmail($to, $subject, $body) {
    if (!function_exists('fsockopen')) return false;
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $toEncoded = '=?UTF-8?B?' . base64_encode($to) . '?= <' . $to . '>';
    $boundary = '=_JM_Alt_Boundary_' . md5(microtime(true) . mt_rand());
    $htmlBody = chunk_split(base64_encode($body));
    $headers  = "From: =?UTF-8?B?" . base64_encode(SMTP_FROM_NAME) . "?= <" . SMTP_FROM . ">\r\n";
    $headers .= "To: $toEncoded\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $connectTimeout = defined('HTTP_CONNECT_TIMEOUT') ? HTTP_CONNECT_TIMEOUT : 5;
    $totalTimeout   = 12; // 邮件最多 12 秒
    $start = microtime(true);

    $errno = 0; $errstr = '';
    $socket = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, $connectTimeout);
    if (!$socket) {
        @file_put_contents(__DIR__ . '/../data/mail_error.log',
            '[' . date('Y-m-d H:i:s') . "] SMTP connect fail: $errno $errstr (to $to)\n", FILE_APPEND);
        return false;
    }
    stream_set_timeout($socket, $totalTimeout, 0);
    stream_set_blocking($socket, true);

    $smtpRead = function($socket) use (&$start, $totalTimeout) {
        $resp = '';
        while (!feof($socket)) {
            if ((microtime(true) - $start) > $totalTimeout) return '';
            $line = @fgets($socket, 515);
            if ($line === false) break;
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $resp;
    };
    $smtpSend = function($socket, $cmd, $expectedCodes) use (&$start, $totalTimeout, $smtpRead) {
        if ((microtime(true) - $start) > $totalTimeout) return false;
        @fwrite($socket, $cmd . "\r\n");
        $resp = $smtpRead($socket);
        if ($resp === '' || !preg_match('/^(\d{3})/', $resp, $m)) return false;
        return in_array(intval($m[1]), (array)$expectedCodes, true);
    };

    try {
        // 220 banner
        $banner = $smtpRead($socket);
        if (!preg_match('/^220\b/', $banner)) {
            @file_put_contents(__DIR__ . '/../data/mail_error.log',
                '[' . date('Y-m-d H:i:s') . "] SMTP bad banner: " . trim($banner) . " (to $to)\n", FILE_APPEND);
            return false;
        }
        if (!$smtpSend($socket, 'EHLO ' . SMTP_HOST, [250])) return false;
        if (!$smtpSend($socket, 'AUTH LOGIN',            [334])) return false;
        if (!$smtpSend($socket, base64_encode(SMTP_USER), [334])) return false;
        if (!$smtpSend($socket, base64_encode(SMTP_PASS), [235])) return false;
        if (!$smtpSend($socket, 'MAIL FROM: <' . SMTP_FROM . '>', [250])) return false;
        if (!$smtpSend($socket, 'RCPT TO: <' . $to . '>',       [250, 251])) return false;
        if (!$smtpSend($socket, 'DATA',                         [354])) return false;

        $msg  = $headers . "\r\n";
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode("请使用 HTML 邮件客户端查看邮件内容")) . "\r\n";
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= $htmlBody . "\r\n";
        $msg .= "--$boundary--\r\n";
        $msg .= ".\r\n";
        if (@fwrite($socket, $msg) === false) return false;
        $dataResp = $smtpRead($socket);
        if (!preg_match('/^250\b/', $dataResp)) return false;
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        return true;
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../data/mail_error.log',
            '[' . date('Y-m-d H:i:s') . '] SMTP Throwable to ' . $to . ': ' . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
// smtpSend 保留别名（防止老代码引用）
if (!function_exists('smtpSend')) {
    function smtpSend($socket, $command) { fwrite($socket, $command . "\r\n"); return fgets($socket, 515); }
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

/**
 * 颜色调整（主题色亮化/暗化）
 * 全局统一放在 functions.php，避免各页面重复定义导致 Cannot redeclare
 */
if (!function_exists('adjustColor')) {
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
}
?>
