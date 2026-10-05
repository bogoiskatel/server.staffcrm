<?php
// Run the same explicit collector once manually or from cron; secrets stay in private configuration.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/Collector.php';
$config=configuration();$sources=$config['statistics_sources']??[];
if(!$sources){fwrite(STDERR,"No statistics sources configured\n");exit(1);}
$db=database($config);$failed=0;
if((int)$db->query("SELECT GET_LOCK('staff_server_collect',0)")->fetchColumn()!==1){fwrite(STDERR,"Collector already running\n");exit(1);}
try{foreach($sources as $source){try{$data=Collector::fetch($source);$r=Collector::store($db,$data,$source,$config);echo 'Collected '.$r['uuid'].' '.$r['period'].' members='.$r['members']."\n";}catch(Throwable $e){$failed++;$code=preg_match('/^http_[0-9]{3}$/D',$e->getMessage())?$e->getMessage():'collection_failed';fwrite(STDERR,$code."\n");if(!empty($source['uuid'])){$q=$db->prepare('SELECT id FROM installations WHERE uuid=?');$q->execute([$source['uuid']]);$id=$q->fetchColumn();if($id)$db->prepare('INSERT INTO collection_attempts(installation_id,reporting_period,attempted_at,success,error_code) VALUES(?,?,?,0,?)')->execute([$id,gmdate('Y-m'),gmdate('Y-m-d H:i:s'),$code]);}}}}finally{$db->query("SELECT RELEASE_LOCK('staff_server_collect')");}
exit($failed?1:0);
