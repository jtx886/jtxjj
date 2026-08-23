<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private $pdo;
    private static $instance = null;

    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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

    private function initTables() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            avatar TEXT DEFAULT '',
            banned INTEGER DEFAULT 0,
            ban_reason TEXT DEFAULT '',
            ban_start DATETIME,
            ban_end DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            code TEXT NOT NULL,
            type TEXT NOT NULL,
            expire_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS play_sources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS announcement_dismissed (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            announcement_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedback_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feedback_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedback_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feedback_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            media_id INTEGER NOT NULL,
            media_type TEXT NOT NULL,
            media_title TEXT NOT NULL,
            media_poster TEXT DEFAULT '',
            media_year TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS watch_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            media_id INTEGER NOT NULL,
            media_type TEXT NOT NULL,
            media_title TEXT NOT NULL,
            media_poster TEXT DEFAULT '',
            season INTEGER DEFAULT 1,
            episode INTEGER DEFAULT 1,
            play_seconds INTEGER DEFAULT 0,
            last_watch DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT
        )");

        $this->initDefaultSettings();
        $this->initDefaultPlaySources();
    }

    private function initDefaultSettings() {
        $this->pdo->exec("INSERT OR IGNORE INTO site_settings (setting_key, setting_value) VALUES ('theme_color', '#8b5cf6')");
        $this->pdo->exec("INSERT OR IGNORE INTO site_settings (setting_key, setting_value) VALUES ('theme_primary', '#8b5cf6')");
        $this->pdo->exec("INSERT OR IGNORE INTO site_settings (setting_key, setting_value) VALUES ('theme_secondary', '#a78bfa')");
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

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach(array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $setStr = implode(', ', $set);
        $params = array_merge($data, $whereParams);
        $this->query("UPDATE $table SET $setStr WHERE $where", $params);
    }

    public function delete($table, $where, $params = []) {
        $this->query("DELETE FROM $table WHERE $where", $params);
    }
}
?>
