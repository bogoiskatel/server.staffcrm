<?php
final class Statistics
{
    const METRICS = ['members_total', 'baptized_this_month', 'baptized_this_year', 'joined_this_year', 'left_this_year', 'groups_current_season', 'discipline_total'];

    // Keep unknown values separate from known zero counts.
    public static function aggregate(array $rows, int $expected): array
    {
        $result = [];
        foreach (self::METRICS as $metric) {
            $sum = 0; $known = 0; $complete = true;
            foreach ($rows as $row) {
                if (isset($row[$metric])) { $sum += (int)$row[$metric]; $known++; if(!self::metricComplete($row,$metric))$complete=false; }
            }
            $result[$metric] = ['value' => $known ? $sum : null, 'known' => $known, 'complete' => $expected > 0 && $known === $expected && $complete];
        }
        return $result;
    }

    // Preserve the API's incomplete-date warning while still displaying known counts.
    public static function metricComplete(array $row, string $metric): bool
    {
        $names=['members_total'=>'members_current','baptized_this_month'=>'baptized_month','baptized_this_year'=>'baptized_year','joined_this_year'=>'members_joined_year','left_this_year'=>'members_left_year','groups_current_season'=>'home_groups_current_season','discipline_total'=>'discipline_current'];
        $metadata=json_decode($row['source_metadata']??'{}',true);
        $state=$metadata['availability'][$names[$metric]??$metric]??[];
        return ($state['complete']??true)!==false && ($state['available']??true)!==false;
    }

    // Prefer the church pair and never replace an invalid pair with a fabricated pin.
    public static function coordinates(array $row): ?array
    {
        $church = isset($row['church_latitude']) || isset($row['church_longitude']);
        $lat = $church ? ($row['church_latitude'] ?? null) : ($row['latitude'] ?? null);
        $lng = $church ? ($row['church_longitude'] ?? null) : ($row['longitude'] ?? null);
        $source = $church ? 'church_coordinates' : ($row['coordinate_source'] ?? null);
        if (!in_array($source, ['church_coordinates', 'postcode_geocoding'], true) || !is_numeric($lat) || !is_numeric($lng)) { return null; }
        $lat = (float)$lat; $lng = (float)$lng;
        if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) { return null; }
        return [$lat, $lng, $source];
    }

    // Accept only explicit calendar month identifiers.
    public static function validPeriod(string $period): bool
    {
        return (bool)preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $period);
    }

    // Validate counters before persisting external statistics.
    public static function counter($value): ?int
    {
        if ($value === null) { return null; }
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$value) || strlen((string)$value) > 9) {
            throw new InvalidArgumentException('Invalid counter');
        }
        return (int)$value;
    }
}
