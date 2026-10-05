<?php
final class Repository
{
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }

    // List registry entries with a single, consistent reporting period.
    public function installations(?string $period): array
    {
        $sql = 'SELECT i.*, s.reporting_period, s.generated_at, s.collected_at, s.members_total, s.baptized_this_month, s.baptized_this_year, s.joined_this_year, s.left_this_year, s.groups_current_season, s.discipline_total, s.last_successful_login,
          (SELECT MAX(collected_at) FROM statistics_snapshots WHERE installation_id=i.id) AS last_collected_at,
          (SELECT success FROM collection_attempts WHERE installation_id=i.id ORDER BY attempted_at DESC, id DESC LIMIT 1) AS latest_success
          FROM installations i LEFT JOIN statistics_snapshots s ON s.installation_id=i.id AND s.reporting_period=:period WHERE i.active=1 ORDER BY i.name, i.uuid';
        $q = $this->db->prepare($sql); $q->execute(['period' => $period]);
        return $q->fetchAll();
    }

    // Return available reporting periods, newest first.
    public function periods(): array
    {
        return $this->db->query('SELECT DISTINCT s.reporting_period FROM statistics_snapshots s JOIN installations i ON i.id=s.installation_id WHERE i.active=1 ORDER BY s.reporting_period DESC')->fetchAll(PDO::FETCH_COLUMN);
    }

    // Load one installation and its immutable historical snapshots.
    public function detail(string $uuid): ?array
    {
        $q = $this->db->prepare('SELECT * FROM installations WHERE uuid=?'); $q->execute([$uuid]); $row = $q->fetch();
        if (!$row) { return null; }
        $q = $this->db->prepare('SELECT * FROM statistics_snapshots WHERE installation_id=? ORDER BY reporting_period DESC'); $q->execute([$row['id']]);
        $row['snapshots'] = $q->fetchAll();
        $q = $this->db->prepare('SELECT reporting_period, attempted_at, success, error_code FROM collection_attempts WHERE installation_id=? ORDER BY attempted_at DESC, id DESC LIMIT 20'); $q->execute([$row['id']]);
        $row['attempts'] = $q->fetchAll();
        return $row;
    }
}
