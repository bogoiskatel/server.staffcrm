<?php
/** Pull only explicitly configured sources and persist validated aggregate snapshots. */
final class Collector
{
    // Require pinned HTTPS endpoints; loopback is an explicit development-only source.
    public static function validateSource(array $source): void
    {
        $url=$source['url']??'';$p=is_string($url)?parse_url($url):false;
        if(!$p||($p['scheme']??'')!=='https'||isset($p['user'],$p['pass'])||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||preg_match('/[\x00-\x20\x7f\\\\]/',$url))throw new InvalidArgumentException('Invalid source URL');
        $host=$p['host']??'';$local=in_array($host,['127.0.0.1','localhost','[::1]'],true);
        if($local!==!empty($source['local']))throw new InvalidArgumentException('Local source must be explicit');
        if(!$local){
            if(isset($p['port'])&&$p['port']!==443)throw new InvalidArgumentException('Invalid source port');
            $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:array_merge(array_column(dns_get_record($host,DNS_A)?:[],'ip'),array_column(dns_get_record($host,DNS_AAAA)?:[],'ipv6'));
            if(!$ips)throw new InvalidArgumentException('Source DNS unavailable');
            foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new InvalidArgumentException('Private source prohibited');
        }
        if(!preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/D',$source['bearer']??''))throw new InvalidArgumentException('Invalid API credential');
    }
    // Reject malformed timestamps instead of normalizing impossible dates.
    private static function timestamp($value): ?string
    {
        if($value===null)return null;
        if(!is_string($value))throw new InvalidArgumentException('Invalid timestamp');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$value,new DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$value)throw new InvalidArgumentException('Invalid timestamp');
        return $d->format('Y-m-d H:i:s');
    }
    // Map the actual CRM v1 fields without inventing monthly counts or missing coordinates.
    public static function normalize(array $data): array
    {
        if(($data['version']??null)!==1||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$data['installation_uuid']??'')||!Statistics::validPeriod($data['observed_period']??''))throw new InvalidArgumentException('Invalid statistics contract');
        $generated=self::timestamp($data['generated_at']??null);if($generated===null)throw new InvalidArgumentException('Missing generated time');
        $row=['uuid'=>$data['installation_uuid'],'name'=>$data['church_name']??'','reporting_period'=>$data['observed_period'],'generated_at'=>$generated,'last_successful_login'=>self::timestamp($data['last_login_at']??null)];
        if(!is_string($row['name'])||strlen($row['name'])>800)throw new InvalidArgumentException('Invalid name');
        if($row['name']==='')$row['name']='STAFF CRM '.$row['uuid'];
        foreach(['members_total'=>'members_current','baptized_this_month'=>'baptized_month','baptized_this_year'=>'baptized_year','joined_this_year'=>'members_joined_year','left_this_year'=>'members_left_year','groups_current_season'=>'home_groups_current_season','discipline_total'=>'discipline_current'] as $to=>$from){
            if($to!=='baptized_this_month'&&!array_key_exists($from,$data))throw new InvalidArgumentException('Missing counter');
            $row[$to]=Statistics::counter($data[$from]??null);
        }
        $country=$data['country_iso']??null;$postcode=$data['postcode']??null;
        if($country!==null&&(!is_string($country)||!preg_match('/^[A-Z]{2}$/D',$country))||$postcode!==null&&(!is_string($postcode)||strlen($postcode)>32))throw new InvalidArgumentException('Invalid location metadata');
        $row['country_iso']=$country;$row['postcode']=$postcode;$point=$data['location']??[];
        $lat=$point['latitude']??null;$lng=$point['longitude']??null;
        if(($lat===null)!==($lng===null)||$lat!==null&&(!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||abs((float)$lat)>90||abs((float)$lng)>180))throw new InvalidArgumentException('Invalid coordinates');
        $row['church_latitude']=$lat===null?null:(float)$lat;$row['church_longitude']=$lng===null?null:(float)$lng;
        return $row;
    }
    // Fetch with a verified TLS certificate, bounded response and no redirect credential leakage.
    public static function fetch(array $source): array
    {
        self::validateSource($source);$c=curl_init($source['url']);$body='';$limit=262144;
        $options=[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$source['bearer'],'Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_WRITEFUNCTION=>static function($curl,$chunk)use(&$body,$limit){if(strlen($body)+strlen($chunk)>$limit)return 0;$body.=$chunk;return strlen($chunk);}];
        if(!empty($source['ca_file']))$options[CURLOPT_CAINFO]=$source['ca_file'];
        if(empty($source['local'])){
            $p=parse_url($source['url']);$host=$p['host'];
            if(!filter_var($host,FILTER_VALIDATE_IP)){$records=dns_get_record($host,DNS_A)?:[];if(!$records)throw new RuntimeException('DNS unavailable');$ip=$records[0]['ip'];if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Private source prohibited');$options[CURLOPT_RESOLVE]=[$host.':443:'.$ip];}
        }
        curl_setopt_array($c,$options);$ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$type=curl_getinfo($c,CURLINFO_CONTENT_TYPE);curl_close($c);
        if($ok===false)throw new RuntimeException('transport_failed');
        if($status!==200)throw new RuntimeException('http_'.$status);
        if(stripos((string)$type,'application/json')!==0)throw new RuntimeException('invalid_content_type');
        $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('invalid_json');return $data;
    }
    // Upsert one snapshot per installation/month atomically; keep raw identity tokens encrypted.
    public static function store(PDO $db,array $data,array $source,array $config): array
    {
        $row=self::normalize($data);
        if(isset($source['uuid'])&&!hash_equals($source['uuid'],$row['uuid']))throw new InvalidArgumentException('Installation identity mismatch');
        $encrypted=null;
        if(isset($data['crm_token'])){
            $secret=base64_decode(trim($config['phone_decryption_key']??''),true);
            if($secret===false||strlen($secret)!==SODIUM_CRYPTO_BOX_SECRETKEYBYTES||!is_string($data['crm_token']))throw new InvalidArgumentException('Encryption key unavailable');
            $pub=sodium_crypto_box_publickey_from_secretkey($secret);$encrypted=json_encode(['algorithm'=>'libsodium-sealedbox','key_id'=>hash('sha256',$pub),'ciphertext'=>base64_encode(sodium_crypto_box_seal(json_encode(['crm_token'=>$data['crm_token']],JSON_THROW_ON_ERROR),$pub))],JSON_THROW_ON_ERROR);
        }
        $db->beginTransaction();
        try{
            // Serialize updates for this UUID and retain current metadata when older snapshots arrive.
            $q=$db->prepare('SELECT i.id,(SELECT MAX(generated_at) FROM statistics_snapshots WHERE installation_id=i.id) AS latest FROM installations i WHERE i.uuid=? FOR UPDATE');
            $q->execute([$row['uuid']]);$existing=$q->fetch();$fresh=!$existing||$existing['latest']===null||$row['generated_at']>=$existing['latest'];
            if($fresh){
            $q=$db->prepare('INSERT INTO installations(uuid,name,api_url,country_iso,postcode,church_latitude,church_longitude,registered_at) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),api_url=VALUES(api_url),country_iso=VALUES(country_iso),postcode=VALUES(postcode),church_latitude=VALUES(church_latitude),church_longitude=VALUES(church_longitude)');
            $q->execute([$row['uuid'],$row['name'],$source['url'],$row['country_iso'],$row['postcode'],$row['church_latitude'],$row['church_longitude'],gmdate('Y-m-d H:i:s')]);
            }
            $q=$db->prepare('SELECT id FROM installations WHERE uuid=?');$q->execute([$row['uuid']]);$id=$q->fetchColumn();
            $fields=array_merge(['installation_id','reporting_period','generated_at','collected_at'],Statistics::METRICS,['last_successful_login','source_metadata']);$values=[$id,$row['reporting_period'],$row['generated_at'],gmdate('Y-m-d H:i:s')];foreach(Statistics::METRICS as $m)$values[]=$row[$m];$values[]=$row['last_successful_login'];$values[]=json_encode(array_intersect_key($data,array_flip(['availability','timezone','year','versions'])),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $q=$db->prepare('INSERT INTO statistics_snapshots('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(static function($f){return $f.'=IF(VALUES(generated_at)>=generated_at,VALUES('.$f.'),'.$f.')';},array_slice($fields,3))).',generated_at=GREATEST(generated_at,VALUES(generated_at))');$q->execute($values);
            if($fresh&&$encrypted!==null)$db->prepare('INSERT INTO installation_credentials(installation_id,phone_encrypted,key_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE phone_encrypted=VALUES(phone_encrypted),key_id=VALUES(key_id)')->execute([$id,$encrypted,hash('sha256',$pub)]);
            $db->prepare('INSERT INTO collection_attempts(installation_id,reporting_period,attempted_at,success) VALUES(?,?,?,1)')->execute([$id,$row['reporting_period'],gmdate('Y-m-d H:i:s')]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
        return ['uuid'=>$row['uuid'],'period'=>$row['reporting_period'],'members'=>$row['members_total']];
    }
}
