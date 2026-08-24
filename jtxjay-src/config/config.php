<?php
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

// 播放器配置
define('PLAYER_PARSER', 'https://svip.ffzyplay.com/?url=');

// 默认播放源
define('DEFAULT_PLAY_SOURCE', 'https://api.yyzy-tv.vip/inc/apijson.php');

// 数据库配置 — 默认MySQL，可改 sqlite
define('DB_TYPE', 'mysql');           // 'mysql' 或 'sqlite'
define('DB_HOST', 'localhost');
define('DB_NAME', 'jay_movies');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PATH', __DIR__ . '/../data/jaymovies.db');  // SQLite 备用路径

// HTTP 超时（秒）— 防止 TMDB 连不上时卡死
define('HTTP_CONNECT_TIMEOUT', 5);   // 连接超时
define('HTTP_TOTAL_TIMEOUT', 10);     // 总超时
define('TMDB_CACHE_TTL', 3600);       // TMDB 缓存有效期（秒）

// 网站配置
define('SITE_NAME', 'Jay影视');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');
define('ADMIN_USER', '杰同学');
define('ADMIN_PASS', '101113');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

session_start();
?>
