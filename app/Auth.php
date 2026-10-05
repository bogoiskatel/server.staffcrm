<?php
final class Auth
{
    private $db; private $config;
    public function __construct(PDO $db, array $config) { $this->db = $db; $this->config = $config; }

    // Use a bounded, rotated session for every authenticated browser.
    public function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        session_name($this->config['session_name']);
        session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>!$this->config['local_http'], 'httponly'=>true, 'samesite'=>'Lax']);
        session_start();
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $this->config['session_timeout']) {
            $_SESSION = []; session_regenerate_id(true);
        }
        if ($this->loggedIn()) { $_SESSION['last_activity'] = time(); }
    }
    public function loggedIn(): bool { return ($_SESSION['authenticated'] ?? false) === true; }
    public function csrf(): string
    {
        if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf'];
    }
    public function validCsrf($value): bool { return is_string($value) && hash_equals($this->csrf(), $value); }

    // Persist all attempts before checking the password, including correct attempts.
    public function login(string $username, string $password, string $source): string
    {
        $hash = hash('sha256', $source);
        $now = gmdate('Y-m-d H:i:s');
        $cutoff = gmdate('Y-m-d H:i:s', time() - $this->config['login_window']);
        // Serialize rate-limit checks across PHP workers, not just within a session.
        $lock = $this->db->prepare('SELECT GET_LOCK(?, 3)'); $lock->execute(['login:'.$hash]);
        if ((int)$lock->fetchColumn() !== 1) { return 'limited'; }
        try {
            $q = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE source_hash=? AND attempted_at >= ?'); $q->execute([$hash, $cutoff]);
            if ((int)$q->fetchColumn() >= $this->config['login_limit']) { return 'limited'; }
            $q = $this->db->prepare('INSERT INTO login_attempts(source_hash,attempted_at) VALUES(?,?)'); $q->execute([$hash,$now]);
            $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([gmdate('Y-m-d H:i:s', time()-86400)]);
            $valid = password_verify($password, $this->config['admin_password_hash']);
            if (!$valid || !hash_equals($this->config['admin_username'], $username)) { return 'invalid'; }
            session_regenerate_id(true);
            $_SESSION = ['authenticated'=>true, 'last_activity'=>time(), 'csrf'=>bin2hex(random_bytes(32))];
            return 'success';
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute(['login:'.$hash]);
        }
    }

    // Destroy both server state and the authentication cookie.
    public function logout(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>$p['path'], 'secure'=>$p['secure'], 'httponly'=>true, 'samesite'=>'Lax']);
        session_destroy();
    }
}
