<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private $pdo;
    private static $instance = null;
    private $driver;

    public function __construct() {
        try {
            if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
                $this->driver = 'mysql';
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            } else {
                $this->driver = 'sqlite';
                $this->pdo = new PDO('sqlite:' . DB_PATH);
            }
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            if ($this->driver === 'mysql') {
                $this->pdo->exec("SET NAMES " . DB_CHARSET);
            }
            $this->initTables();
        } catch(PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDriver() {
        return $this->driver;
    }

    // 返回 SQL 方言相关关键字
    private function autoInc() {
        return $this->driver === 'mysql' ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
    }
    // INSERT OR IGNORE (SQLite) / INSERT IGNORE (MySQL)
    private function insertIgnore() {
        return $this->driver === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    }

    private function initTables() {
        $ai = $this->autoInc();

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY $ai,
            email VARCHAR(255) UNIQUE NOT NULL,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            avatar VARCHAR(500) DEFAULT '',
            banned INTEGER DEFAULT 0,
            ban_reason TEXT,
            ban_start DATETIME,
            ban_end DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_codes (
            id INTEGER PRIMARY KEY $ai,
            email VARCHAR(255) NOT NULL,
            code VARCHAR(20) NOT NULL,
            type VARCHAR(20) NOT NULL,
            expire_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS play_sources (
            id INTEGER PRIMARY KEY $ai,
            name VARCHAR(100) NOT NULL,
            url TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY $ai,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS announcement_dismissed (
            id INTEGER PRIMARY KEY $ai,
            user_id INTEGER NOT NULL,
            announcement_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
            id INTEGER PRIMARY KEY $ai,
            user_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedback_replies (
            id INTEGER PRIMARY KEY $ai,
            feedback_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedback_likes (
            id INTEGER PRIMARY KEY $ai,
            feedback_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id INTEGER PRIMARY KEY $ai,
            user_id INTEGER NOT NULL,
            media_id INTEGER NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            media_title VARCHAR(255) NOT NULL,
            media_poster VARCHAR(500) DEFAULT '',
            media_year VARCHAR(10) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS watch_history (
            id INTEGER PRIMARY KEY $ai,
            user_id INTEGER NOT NULL,
            media_id INTEGER NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            media_title VARCHAR(255) NOT NULL,
            media_poster VARCHAR(500) DEFAULT '',
            season INTEGER DEFAULT 1,
            episode INTEGER DEFAULT 1,
            play_seconds INTEGER DEFAULT 0,
            last_watch DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            id INTEGER PRIMARY KEY $ai,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT
        )");

        // TMDB 缓存表 — 缓存 API 请求结果，减少重复请求
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tmdb_cache (
            id INTEGER PRIMARY KEY $ai,
            cache_key VARCHAR(64) UNIQUE NOT NULL,
            cache_value LONGTEXT,
            expire_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->initDefaultSettings();
        $this->initDefaultPlaySources();
    }

    private function initDefaultSettings() {
        $ig = $this->insertIgnore();
        $this->pdo->exec("$ig INTO site_settings (setting_key, setting_value) VALUES ('theme_color', '#8b5cf6')");
        $this->pdo->exec("$ig INTO site_settings (setting_key, setting_value) VALUES ('theme_primary', '#8b5cf6')");
        $this->pdo->exec("$ig INTO site_settings (setting_key, setting_value) VALUES ('theme_secondary', '#a78bfa')");
    }

    private function initDefaultPlaySources() {
        $check = $this->pdo->query("SELECT COUNT(*) FROM play_sources")->fetchColumn();
        if($check == 0) {
            $this->pdo->exec("INSERT INTO play_sources (name, url, is_default) VALUES 
                ('YYZY资源', '" . DEFAULT_PLAY_SOURCE . "', 1)");
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($table, $data) {
        $keys = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $this->query("INSERT INTO $table ($keys) VALUES ($placeholders)", $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * UPDATE — 自动把 WHERE 子句中的 ? 占位符转换为命名占位符 :w0, :w1, ...
     * 避免 SET 用命名占位符 + WHERE 用 ? 占位符混合导致 PDO 报错
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach(array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $setStr = implode(', ', $set);
        list($whereNamed, $namedParams) = $this->positionalToNamed($where, $whereParams, 'w');
        $params = array_merge($data, $namedParams);
        $this->query("UPDATE $table SET $setStr WHERE $whereNamed", $params);
    }

    public function delete($table, $where, $params = []) {
        // 若只用了 ? 位置占位符，直接执行；若与命名占位符混用，统一转换为命名占位符
        if (strpos($where, '?') !== false && preg_match('/:/', $where) === 0) {
            $this->query("DELETE FROM $table WHERE $where", $params);
        } else {
            list($whereNamed, $namedParams) = $this->positionalToNamed($where, $params, 'd');
            $this->query("DELETE FROM $table WHERE $whereNamed", $namedParams);
        }
    }

    /**
     * 把 SQL 片段里的 ? 占位符依次替换为 :{prefix}0, :{prefix}1, ...
     * 并把 params 数组 keys 从 0..n-1 重写为对应的命名 key
     */
    private function positionalToNamed($sqlFragment, $params, $prefix) {
        // 已经全部用命名占位符（没有 ?），直接返回
        if (strpos($sqlFragment, '?') === false) {
            $named = [];
            foreach((array)$params as $k => $v) {
                if (is_int($k)) { $named[":$prefix$k"] = $v; }
                else { $named[$k] = $v; }
            }
            return [$sqlFragment, $named];
        }
        $named = [];
        $i = 0;
        $parts = preg_split('/\?/', $sqlFragment);
        $out = '';
        foreach($parts as $idx => $seg) {
            $out .= $seg;
            if ($idx < count($parts) - 1) {
                $key = ":$prefix$i";
                $out .= $key;
                $named[$key] = array_values($params)[$i] ?? null;
                $i++;
            }
        }
        return [$out, $named];
    }

    // ===== TMDB 缓存方法 =====

    /** 获取 TMDB 缓存 */
    public function tmdbCacheGet($key) {
        $nowFn = $this->driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $row = $this->fetchOne(
            "SELECT cache_value FROM tmdb_cache WHERE cache_key = ? AND expire_at > $nowFn",
            [$key]
        );
        if ($row) {
            $data = json_decode($row['cache_value'], true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    /** 写入 TMDB 缓存 */
    public function tmdbCacheSet($key, $data, $ttl = null) {
        if ($ttl === null) $ttl = defined('TMDB_CACHE_TTL') ? TMDB_CACHE_TTL : 3600;
        $expire = date('Y-m-d H:i:s', time() + $ttl);
        $value = json_encode($data, JSON_UNESCAPED_UNICODE);
        // 先删旧的再插入（兼容 MySQL 和 SQLite）
        $this->delete('tmdb_cache', 'cache_key = ?', [$key]);
        $this->insert('tmdb_cache', [
            'cache_key'   => $key,
            'cache_value' => $value,
            'expire_at'   => $expire,
        ]);
    }
}
?>
