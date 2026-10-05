<?php
// Run the semantic tests without external test dependencies.
$file = __DIR__ . '/../app/Statistics.php';
if (!is_file($file)) { fwrite(STDERR, "FAIL: Statistics implementation is missing\n"); exit(1); }
require $file;
$checks = 0;
function check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
$partial=Statistics::aggregate([['left_this_year'=>0,'source_metadata'=>json_encode(['availability'=>['members_left_year'=>['complete'=>false]]])]],1);
check($partial['left_this_year']['value']===0&&!$partial['left_this_year']['complete'],'Known zero with missing dates must remain incomplete');
$rows = [['members_total' => 12, 'baptized_this_month' => 0], ['members_total' => null, 'baptized_this_month' => 3]];
$sum = Statistics::aggregate($rows, 3);
check($sum['members_total']['value'] === 12, 'Known members must be summed');
check($sum['members_total']['known'] === 1 && !$sum['members_total']['complete'], 'Partial totals need coverage');
check($sum['baptized_this_month']['value'] === 3 && $sum['baptized_this_month']['known'] === 2, 'Zero is a known value');
check(Statistics::aggregate([], 0)['members_total']['value'] === null, 'Empty database is unknown, not zero');
check(Statistics::aggregate([['members_total'=>0]], 1)['members_total']['complete'], 'Known zero can be complete');
check(Statistics::coordinates(['church_latitude'=>0,'church_longitude'=>0]) === [0.0,0.0,'church_coordinates'], 'Zero coordinate must be retained');
check(Statistics::coordinates(['church_latitude'=>91,'church_longitude'=>1]) === null, 'Invalid latitude must not create a marker');
check(Statistics::coordinates(['church_latitude'=>null,'church_longitude'=>null,'latitude'=>50,'longitude'=>30,'coordinate_source'=>'postcode_geocoding']) === [50.0,30.0,'postcode_geocoding'], 'Validated cached fallback can create a marker');
check(Statistics::coordinates(['church_latitude'=>0,'church_longitude'=>null]) === null, 'Partial pair cannot create a marker');
check(Statistics::coordinates(['church_latitude'=>INF,'church_longitude'=>0]) === null, 'Infinite coordinate is invalid');
check(Statistics::validPeriod('2026-09') && !Statistics::validPeriod('2026-13') && !Statistics::validPeriod('x'), 'Period validation');
check(Statistics::counter(null) === null && Statistics::counter(0) === 0 && Statistics::counter('12') === 12, 'Counter normalization');
foreach ([-1, 1.5, '2e3', 'abc'] as $value) {
    try { Statistics::counter($value); check(false, 'Invalid counter accepted'); } catch (InvalidArgumentException $e) { check(true, 'Invalid counter rejected'); }
}
echo "PASS: $checks semantic checks\n";
