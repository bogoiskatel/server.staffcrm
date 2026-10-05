<?php
// Verify repeated polling, stale responses, identity binding and encrypted contact storage in server_test only.
putenv('STAFF_SERVER_CONFIG='. __DIR__.'/.local/config.php');
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/Collector.php';
$config=configuration();if(strpos($config['db_dsn'],'dbname=server_test;')===false)throw new RuntimeException('Isolated test database required');
$db=database($config);$uuid='44444444-4444-4444-8444-444444444444';
$keypair=sodium_crypto_box_keypair();$config['phone_decryption_key']=base64_encode(sodium_crypto_box_secretkey($keypair));
$source=['url'=>'https://example.invalid/api/','uuid'=>$uuid];
$data=['version'=>1,'installation_uuid'=>$uuid,'church_name'=>'Collector fixture','observed_period'=>'2026-10','generated_at'=>'2026-10-05T13:00:00Z','members_current'=>12,'members_joined_year'=>2,'members_left_year'=>null,'baptized_year'=>3,'home_groups_current_season'=>0,'discipline_current'=>null,'last_login_at'=>null,'country_iso'=>'UA','postcode'=>'00123','location'=>['latitude'=>0,'longitude'=>0],'availability'=>['members_left_year'=>['available'=>true,'complete'=>false,'reason'=>'missing_departure_dates']],'crm_token'=>'test-contact-token'];
Collector::store($db,$data,$source,$config);Collector::store($db,$data,$source,$config);
$q=$db->prepare('SELECT s.* FROM statistics_snapshots s JOIN installations i ON i.id=s.installation_id WHERE i.uuid=?');$q->execute([$uuid]);$rows=$q->fetchAll();
if(Statistics::metricComplete($rows[0],'left_this_year'))throw new RuntimeException('Incomplete API metadata lost');
if(count($rows)!==1||$rows[0]['baptized_this_month']!==null||$rows[0]['left_this_year']!==null||(int)$rows[0]['groups_current_season']!==0)throw new RuntimeException('Snapshot duplication or null/zero loss');
$older=$data;$older['generated_at']='2026-10-04T13:00:00Z';$older['members_current']=999;$older['church_name']='Stale church';$older['postcode']='99999';$older['location']=['latitude'=>20,'longitude'=>20];$older['crm_token']='stale-token';Collector::store($db,$older,$source,$config);$q->execute([$uuid]);if((int)$q->fetch()['members_total']!==12)throw new RuntimeException('Stale snapshot overwrote data');
$metadataQuery=$db->prepare('SELECT i.name,i.postcode,i.church_latitude,c.phone_encrypted FROM installations i JOIN installation_credentials c ON c.installation_id=i.id WHERE i.uuid=?');$metadataQuery->execute([$uuid]);$metadata=$metadataQuery->fetch();
$oldBlob=json_decode($metadata['phone_encrypted'],true);$oldToken=json_decode(sodium_crypto_box_seal_open(base64_decode($oldBlob['ciphertext']),$keypair),true);
if($metadata['name']!=='Collector fixture'||$metadata['postcode']!=='00123'||(float)$metadata['church_latitude']!==0.0||$oldToken['crm_token']!=='test-contact-token')throw new RuntimeException('Stale response overwrote installation metadata/token');
$newer=$data;$newer['generated_at']='2026-10-06T13:00:00Z';$newer['members_current']=13;Collector::store($db,$newer,$source,$config);$q->execute([$uuid]);if((int)$q->fetch()['members_total']!==13)throw new RuntimeException('Fresh snapshot missing');
try{Collector::store($db,$data,['url'=>$source['url'],'uuid'=>'55555555-5555-4555-8555-555555555555'],$config);throw new RuntimeException('Identity mismatch accepted');}catch(InvalidArgumentException $expected){}
$q=$db->prepare('SELECT phone_encrypted FROM installation_credentials c JOIN installations i ON i.id=c.installation_id WHERE i.uuid=?');$q->execute([$uuid]);$sealed=$q->fetchColumn();
if(strpos($sealed,'test-contact-token')!==false)throw new RuntimeException('Plaintext token stored');
$blob=json_decode($sealed,true);$opened=sodium_crypto_box_seal_open(base64_decode($blob['ciphertext']),$keypair);
if(json_decode($opened,true)['crm_token']!=='test-contact-token')throw new RuntimeException('Stored token cannot decrypt');
$id=$db->query("SELECT id FROM installations WHERE uuid='".$uuid."'")->fetchColumn();
foreach(['installation_credentials','collection_attempts','statistics_snapshots'] as $table)$db->prepare('DELETE FROM '.$table.' WHERE installation_id=?')->execute([$id]);$db->prepare('DELETE FROM installations WHERE id=?')->execute([$id]);
echo "PASS: collector duplicate/stale/fresh snapshots, bound identity, encrypted contact roundtrip\n";
