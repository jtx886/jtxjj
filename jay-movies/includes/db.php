<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private $pdo;
    private $driver;
    private static $instance = null;

    public function __construct() {
        $this->driver = (DB_TYPE === 'mysql') ? 'mysql' : 'sqlite';
        try {
            if ($this->driver === 'mysql') {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } else {
                if (!is_dir(dirname(DB_PATH))) {
                    @mkdir(dirname(DB_PATH), 0777, true);
                }
                $this->pdo = new PDO('sqlite:' . DB_PATH);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec('PRAGMA journal_mode = WAL;');
                $this->pdo->exec('PRAGMA busy_timeout = 3000;');
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
            }
            $this->initTables();
        } catch(Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8', true, 500);
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

    /**
     * 兼容 MySQL 和 SQLite 的建表
     */
    private function autoInc() {
        return $this->driver === 'mysql' ? 'INT NOT NULL AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }
    private function pk() {
        return $this->driver === 'mysql' ? ', PRIMARY KEY (id)' : '';
    }
    private function textType() {
        return $this->driver === 'mysql' ? 'TEXT' : 'TEXT';
    }

    private function execDDL($sql) {
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $e) {
            // 忽略"表已存在"类型的错误
        }
    }

    private function initTables() {
        $ai = $this->autoInc();
        $pk = $this->pk();

        if ($this->driver === 'mysql') {
            $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        } else {
            $engine = '';
        }

        $this->execDDL("CREATE TABLE IF NOT EXISTS users (
            id $ai,
            email VARCHAR(255) NOT NULL UNIQUE,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            avatar TEXT DEFAULT '',
            banned TINYINT DEFAULT 0,
            ban_reason TEXT DEFAULT '',
            ban_start DATETIME NULL,
            ban_end DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS email_codes (
            id $ai,
            email VARCHAR(255) NOT NULL,
            code VARCHAR(20) NOT NULL,
            type VARCHAR(30) NOT NULL,
            expire_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");
        if ($this->driver === 'mysql') {
            $this->execDDL("CREATE INDEX IF NOT EXISTS idx_email_codes_email ON email_codes(email)");
        }

        $this->execDDL("CREATE TABLE IF NOT EXISTS play_sources (
            id $ai,
            name VARCHAR(100) NOT NULL,
            url VARCHAR(500) NOT NULL,
            parser_url VARCHAR(500) DEFAULT '',
            is_default TINYINT DEFAULT 0,
            sort INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS announcements (
            id $ai,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS announcement_dismissed (
            id $ai,
            user_id INT NOT NULL,
            announcement_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS feedbacks (
            id $ai,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS feedback_replies (
            id $ai,
            feedback_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            is_admin TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS feedback_likes (
            id $ai,
            feedback_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS favorites (
            id $ai,
            user_id INT NOT NULL,
            media_id INT NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            media_title VARCHAR(255) NOT NULL,
            media_poster TEXT DEFAULT '',
            media_year VARCHAR(10) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS watch_history (
            id $ai,
            user_id INT NOT NULL,
            media_id INT NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            media_title VARCHAR(255) NOT NULL,
            media_poster TEXT DEFAULT '',
            season INT DEFAULT 1,
            episode INT DEFAULT 1,
            play_seconds INT DEFAULT 0,
            last_watch DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            $pk
        )$engine");

        $this->execDDL("CREATE TABLE IF NOT EXISTS site_settings (
            id $ai,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT
            $pk
        )$engine");

        // TMDB API 缓存表：解决分季加载慢、外部API不稳定的问题
        $this->execDDL("CREATE TABLE IF NOT EXISTS tmdb_cache (
            id $ai,
            cache_key VARCHAR(150) NOT NULL UNIQUE,
            cache_value MEDIUMTEXT,
            expire_at INT NOT NULL,
            created_at INT NOT NULL
            $pk
        )$engine");

        $this->initDefaultSettings();
        $this->initDefaultPlaySources();
    }

    private function initDefaultSettings() {
        $settings = [
            'theme_color' => '#8b5cf6',
            'theme_primary' => '#8b5cf6',
            'theme_secondary' => '#a78bfa',
        ];
        foreach ($settings as $k => $v) {
            $row = $this->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = ?", [$k]);
            if (!$row) {
                $this->insert('site_settings', ['setting_key' => $k, 'setting_value' => $v]);
            }
        }
    }

    private function initDefaultPlaySources() {
        $check = $this->pdo->query("SELECT COUNT(*) FROM play_sources")->fetchColumn();
        if(intval($check) === 0) {
            $this->insert('play_sources', [
                'name'       => 'YYZY资源',
                'url'        => DEFAULT_PLAY_SOURCE,
                'parser_url' => PLAYER_PARSER,
                'is_default' => 1,
                'sort'       => 1,
            ]);
        } else {
            // 升级：老版本缺少 parser_url 字段，兼容补齐
            try {
                $this->pdo->query("SELECT parser_url FROM play_sources LIMIT 1");
            } catch (Throwable $e) {
                $this->execDDL($this->driver === 'mysql'
                    ? "ALTER TABLE play_sources ADD COLUMN parser_url VARCHAR(500) DEFAULT ''"
                    : "ALTER TABLE play_sources ADD COLUMN parser_url TEXT DEFAULT ''");
                try {
                    $this->execDDL($this->driver === 'mysql'
                        ? "ALTER TABLE play_sources ADD COLUMN sort INT DEFAULT 0"
                        : "ALTER TABLE play_sources ADD COLUMN sort INT DEFAULT 0");
                } catch (Throwable $e) {}
                $this->update('play_sources', ['parser_url' => PLAYER_PARSER], 'is_default = 1');
            }
        }
    }

    // ================= TMDB 缓存 =================
    public function tmdbCacheGet($key) {
        $now = time();
        $row = $this->fetchOne("SELECT cache_value, expire_at FROM tmdb_cache WHERE cache_key = ?", [$key]);
        if (!$row) return null;
        if (intval($row['expire_at']) < $now) {
            $this->delete('tmdb_cache', 'cache_key = ?', [$key]);
            return null;
        }
        $data = @json_decode($row['cache_value'], true);
        return is_array($data) ? $data : null;
    }
    public function tmdbCacheSet($key, $value, $ttl = null) {
        if ($ttl === null) $ttl = TMDB_CACHE_TTL;
        $now = time();
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($this->driver === 'mysql') {
            $this->query("INSERT INTO tmdb_cache (cache_key, cache_value, expire_at, created_at) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expire_at = VALUES(expire_at)",
                [$key, $json, $now + $ttl, $now]);
        } else {
            $this->query("INSERT OR REPLACE INTO tmdb_cache (cache_key, cache_value, expire_at, created_at) VALUES (?, ?, ?, ?)",
                [$key, $json, $now + $ttl, $now]);
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
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function insert($table, $data) {
        $keys = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $this->query("INSERT INTO $table ($keys) VALUES ($placeholders)", $data);
        return intval($this->pdo->lastInsertId());
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        $namedParams = [];
        foreach($data as $k => $v) {
            $set[] = "$k = :set_$k";
            $namedParams[":set_$k"] = $v;
        }
        $setStr = implode(', ', $set);
        $params = array_merge($namedParams, $whereParams);
        $this->query("UPDATE $table SET $setStr WHERE $where", $params);
    }

    public function delete($table, $where, $params = []) {
        $this->query("DELETE FROM $table WHERE $where", $params);
    }
}
?>
