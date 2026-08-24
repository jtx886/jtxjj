<?php
// ============================================================
// 数据库驱动选择：DB_TYPE = 'mysql' 或 'sqlite'
// InfinityFree 用户请填写下方 MySQL 账号密码，然后把 DB_TYPE 改成 'mysql'
// ============================================================
define('DB_TYPE', 'sqlite');   // 改成 mysql 即使用 MySQL
define('DB_PATH', __DIR__ . '/../data/jaymovies.db'); // SQLite 文件路径
// --- MySQL 配置（DB_TYPE=mysql 时生效）---
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'jaymovies');      // InfinityFree 创建的数据库名
define('DB_USER', 'root');           // 数据库用户名
define('DB_PASS', '');               // 数据库密码
define('DB_CHARSET', 'utf8mb4');

// SMTP配置
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

// TMDB API配置
define('TMDB_API_KEY', 'cb44223c5dee5676ed3a839f42ed27e3');
define('TMDB_READ_TOKEN', 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjYjQ0MjIzYzVkZWU1Njc2ZWQzYTgzOWY0MmVkMjdlZSIsInN1YiI6Njc3MjkyNzg1MzcyNDk4OTg3YjBkZDdkIiwic2NvcGVzIjpbImFwaV9yZWFkIl0sImF1ZCI6IjYyZmI3YjZlZDBlN2VjNjdlYTgyZDdlOSIsImlhdCI6MTcyNDQyMTMxNX0.95UWM3wql05P9SnJf0Py9NNjMikjsXSNGX7a6i6t4qs');
define('TMDB_LANG', 'zh-CN');
define('TMDB_IMG_URL', 'https://image.tmdb.org/t/p/');
define('TMDB_CACHE_TTL', 86400); // 缓存过期时间(秒)，默认24小时

// 播放器配置
define('PLAYER_PARSER', 'https://svip.ffzyplay.com/?url=');

// 默认播放源
define('DEFAULT_PLAY_SOURCE', 'https://api.yyzy-tv.vip/inc/apijson.php');

// 网站配置
define('SITE_NAME', 'Jay影视');
if (!isset($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = 'localhost';
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');
define('ADMIN_USER', '杰同学');
define('ADMIN_PASS', '101113');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告：绝对不允许把错误/警告直接输出到浏览器！
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/../data')) @mkdir(__DIR__ . '/../data', 0777, true);
ini_set('error_log', __DIR__ . '/../data/php_error.log');

session_start();
?>
