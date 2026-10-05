#!/usr/bin/env python3
"""Provision the mutable local CRM copy and connect its API to STAFF SERVER."""
import importlib.util
import json
import os
from pathlib import Path
import secrets
import shlex
import signal
import subprocess
import time
import ssl
import urllib.request
import urllib.error

root = Path(__file__).resolve().parents[1]
crm = Path('/Users/sergiysologub/Code/staffcrm.dev')
state = root/'work/integration'; state.mkdir(parents=True, exist_ok=True)
runtime = crm/'.local/demo'; web = runtime/'web-synthetic'
php = '/opt/homebrew/opt/php@7.4/bin/php'
mysql = '/opt/homebrew/opt/mariadb/bin/mariadb'
working = json.loads((runtime/'working.json').read_text())['database']
# The user's existing private X25519 key supplies the pinned recipient public key.
public = subprocess.check_output([php,'-r', '$c=require $argv[1];$s=base64_decode(trim($c["phone_decryption_key"]),true);if($s===false||strlen($s)!==32)exit(1);echo base64_encode(sodium_crypto_box_publickey_from_secretkey($s));',str(root/'config.php')], text=True)

def private(path, value):
    path.write_text(json.dumps(value)); path.chmod(0o600)

# Permit an explicit loopback address only in the already isolated runtime copy.
protocol = web/'crm_admin/classes/api/ApiProtocol.php'
text = protocol.read_text()
marker = '// Local TLS integration: runtime copy only; product validation remains unchanged.'
if marker not in text:
    text = text.replace('static function canonicalUrl($url) {', 'static function canonicalUrl($url) {\n        '+marker+'\n        if(getenv("STAFFCRM_LOCAL_DEMO")==="1" && is_string($url) && in_array(parse_url($url,PHP_URL_HOST),["127.0.0.1","localhost","[::1]"],true) && parse_url($url,PHP_URL_SCHEME)==="https") return self::addressUrl($url);',1)
    protocol.write_text(text)
operator = {'database':{'host':'localhost','socket':str(runtime/'mysql.sock'),'user':'root','password':'','name':working},'canonical_crm_url':'https://127.0.0.1:8766/','server_public_key':public,'active_theme':'original'}
private(state/'operator.json',operator)
env = {**os.environ, 'STAFFCRM_LOCAL_DEMO':'1'}
cli = web/'crm_admin/migrations/20261004_statistics_api.php'
for action in ['configure-address','initialize']:
    result=json.loads(subprocess.check_output([php,str(cli),'--config',str(state/'operator.json'),'--action',action],env=env,text=True))
    if action=='initialize': installation_uuid=result['installation_uuid']
credentials = state/'crm-credentials.json'
if not credentials.exists():
    issued = json.loads(subprocess.check_output([php,str(cli),'--config',str(state/'operator.json'),'--action','issue'],env=env,text=True))
    private(credentials, {'bearer':issued['secret'],'db_password':secrets.token_hex(24)})
secret = json.loads(credentials.read_text())
# Restrict the HTTP account to business SELECT and the API's rate/audit writes.
sql = "CREATE USER IF NOT EXISTS 'staff_server_api'@'localhost' IDENTIFIED BY '"+secret['db_password']+"';"
for table in ['contacts','settings','groups','groups_seasons','contacts_discipline','contacts_discipline_types','api_access_keys','api_login_state']:
    sql += f"GRANT SELECT ON `{working}`.`{table}` TO 'staff_server_api'@'localhost';"
sql += f"GRANT SELECT,INSERT,UPDATE ON `{working}`.api_rate_limits TO 'staff_server_api'@'localhost'; GRANT INSERT ON `{working}`.api_request_audit TO 'staff_server_api'@'localhost';"
subprocess.run([mysql,'--no-defaults','--socket='+str(runtime/'mysql.sock'),'-u','root'],input=sql,text=True,check=True,stdout=subprocess.DEVNULL)
http_config = dict(operator);http_config['database'] = {**operator['database'],'user':'staff_server_api','password':secret['db_password']}
private(state/'crm-api.json',http_config)
if not (state/'tls.crt').exists():
    (state/'tls.cnf').write_text('[req]\ndistinguished_name=dn\nx509_extensions=ext\nprompt=no\n[dn]\nCN=localhost\n[ext]\nsubjectAltName=DNS:localhost,IP:127.0.0.1\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n')
    subprocess.run(['/usr/bin/openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','365','-config',str(state/'tls.cnf'),'-keyout',str(state/'tls.key'),'-out',str(state/'tls.crt')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    (state/'tls.key').chmod(0o600)
# Restart only the known loopback CRM PHP process, retaining its original options.
records = json.loads((runtime/'processes.json').read_text()); pid=records['web']['pid']
command = subprocess.check_output(['ps','-p',str(pid),'-o','command='],text=True).strip()
if str(web) not in command or '127.0.0.1:8765' not in command:
    raise RuntimeError('Unexpected CRM process; preserved')
os.kill(pid,signal.SIGTERM)
for _ in range(100):
    if not subprocess.run(['ps','-p',str(pid)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0: break
    time.sleep(.1)
clones = json.loads((runtime/'clones.json').read_text()); crm_clones={}
for role in ['crm_october','crm_april']:
    db = working if role=='crm_april' else clones[role]['database']
    member = subprocess.check_output([mysql,'--no-defaults','--socket='+str(runtime/'mysql.sock'),'-u','root','-N','-B',db,'-e','SELECT ID FROM contacts ORDER BY CAST(capabilities AS UNSIGNED) DESC,ID LIMIT 1'],text=True).strip()
    crm_clones[role]={'database':db,'member_id':int(member) if member.isdigit() else None,'contacts':clones[role]['counts']['contacts'],'label':Path(clones[role]['path']).name}
env.update({'STAFFCRM_API_CONFIG':str(state/'crm-api.json'),'STAFFCRM_API_TLS_TERMINATED':'1','STAFFCRM_DEMO_WEB':str(web),'STAFFCRM_DEMO_CRM_CLONES':json.dumps(crm_clones),'STAFFCRM_DEMO_INBOX':str(runtime/'telegram.jsonl')})
with (runtime/'web.log').open('ab') as log:
    process=subprocess.Popen(shlex.split(command),cwd=crm,env=env,stdout=log,stderr=log,start_new_session=True)
records['web']={'pid':process.pid,'identity':subprocess.check_output(['ps','-p',str(process.pid),'-o','lstart=','-o','command='],text=True).strip()}
(runtime/'processes.json').write_text(json.dumps(records))
proxy_pid=state/'tls-proxy.pid'
alive=False
if proxy_pid.exists():
    record=subprocess.run(['ps','-p',proxy_pid.read_text().strip(),'-o','command='],capture_output=True,text=True)
    alive=record.returncode==0 and str(root/'local/crm-tls-proxy.py') in record.stdout
if not alive:
    with (state/'tls-proxy.log').open('ab') as log:
        p=subprocess.Popen(['python3',str(root/'local/crm-tls-proxy.py')],stdout=log,stderr=log,start_new_session=True)
    proxy_pid.write_text(str(p.pid))
private(state/'sources.json',[{'uuid':installation_uuid,'url':'https://127.0.0.1:8766/api/','bearer':secret['bearer'],'local':True,'ca_file':str(state/'tls.crt')}])
config=root/'config.php';text=config.read_text()
if "work/integration/sources.json" not in text:
    text=text.replace("['local_http'=>true,", "['statistics_sources'=>json_decode(file_get_contents(__DIR__.'/work/integration/sources.json'),true),'local_http'=>true,",1)
    config.write_text(text);config.chmod(0o600)
context=ssl.create_default_context(cafile=str(state/'tls.crt'))
for attempt in range(100):
    try:
        urllib.request.urlopen('https://127.0.0.1:8766/api/',context=context,timeout=1)
    except urllib.error.HTTPError as error:
        if error.code==401: break
    except OSError: pass
    time.sleep(.1)
else: raise RuntimeError('Local API did not become ready')
print('Local CRM API configured with bearer authentication and verified TLS on port 8766.')
