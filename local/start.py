#!/usr/bin/env python3
"""Start isolated MariaDB/PHP processes without touching existing CRM services."""
import json, os, secrets, socket, subprocess, time
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
STATE = ROOT / 'work/runtime'
PHP = os.environ.get('STAFF_PHP', '/opt/homebrew/opt/php@7.4/bin/php')
PREFIX = Path(os.environ.get('STAFF_MARIADB', '/opt/homebrew/opt/mariadb'))
CLIENT = str(PREFIX / 'bin/mariadb')
STATE.mkdir(parents=True, exist_ok=True); STATE.chmod(0o700)
SOCKET = str(STATE / 'mysql.sock')
def sql(text, database=None):
    args = [CLIENT, '--no-defaults', '--socket='+SOCKET, '-u', 'root']
    if database: args.append(database)
    return subprocess.run(args, input=text, text=True, capture_output=True, check=True).stdout
def ready():
    return subprocess.run([CLIENT,'--no-defaults','--socket='+SOCKET,'-u','root','-e','SELECT 1'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode == 0
if not (STATE/'mysql/mysql').is_dir():
    with (STATE/'init.log').open('w') as log:
        subprocess.run([str(PREFIX/'bin/mariadb-install-db'),'--no-defaults','--datadir='+str(STATE/'mysql'),'--auth-root-authentication-method=normal'],stdout=log,stderr=log,check=True)
if not ready():
    with (STATE/'db-launch.log').open('ab') as log:
        subprocess.Popen([str(PREFIX/'bin/mariadbd'),'--no-defaults','--datadir='+str(STATE/'mysql'),'--socket='+SOCKET,'--pid-file='+str(STATE/'mysql.pid'),'--skip-networking','--log-error='+str(STATE/'mysql.log'),'--sql-mode=STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'],stdout=log,stderr=log,start_new_session=True)
    for _ in range(100):
        if ready(): break
        time.sleep(.2)
    else: raise RuntimeError('MariaDB did not start; see work/runtime/mysql.log')
(ROOT/'tests/.local').mkdir(parents=True,exist_ok=True)
config = ROOT/'config.php'
if not config.exists():
    dbpass = secrets.token_hex(24); password = secrets.token_urlsafe(20)
    hashed = subprocess.check_output([PHP,'-r','echo password_hash(trim(stream_get_contents(STDIN)),PASSWORD_DEFAULT);'],input=password.encode()).decode()
    sql("CREATE DATABASE IF NOT EXISTS server CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS server_test CHARACTER SET utf8mb4; "
        "CREATE USER IF NOT EXISTS 'staff_server'@'localhost' IDENTIFIED BY '"+dbpass+"'; "
        "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,INDEX,ALTER,REFERENCES ON server.* TO 'staff_server'@'localhost'; "
        "CREATE USER IF NOT EXISTS 'staff_server_test'@'localhost' IDENTIFIED BY '"+dbpass+"'; "
        "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,INDEX,ALTER,REFERENCES ON server_test.* TO 'staff_server_test'@'localhost';")
    content = "<?php\n$db_dsn = 'mysql:unix_socket="+SOCKET+";dbname=server;charset=utf8mb4';\n$db_username = 'staff_server';\n$db_password = '"+dbpass+"';\n$admin_username = 'admin';\n$admin_password_hash = '"+hashed+"';\nreturn array_merge(require __DIR__.'/config.example.php', compact('db_dsn','db_username','db_password','admin_username','admin_password_hash'));\n"
    # Keep variable assignments after example loading so they are not overwritten.
    content = content.replace("<?php\n", "<?php\n$defaults = require __DIR__.'/config.example.php';\n$phone_decryption_key = '';\n").replace("array_merge(require __DIR__.'/config.example.php',", "array_merge($defaults,")
    content = content.replace("compact('db_dsn','db_username','db_password','admin_username','admin_password_hash')", "compact('db_dsn','db_username','db_password','admin_username','admin_password_hash'), ['local_http'=>true,'phpmyadmin_url'=>'http://127.0.0.1:8092/']")
    content = content.replace("'admin_password_hash')", "'admin_password_hash','phone_decryption_key')")
    config.write_text(content); config.chmod(0o600)
    access = ROOT/'outputs/local-access.txt'
    access.write_text('STAFF SERVER\nURL: http://127.0.0.1:8090\nUsername: admin\nPassword: '+password+'\n\nPrivate local credentials. Do not publish.\n'); access.chmod(0o600)
    testconfig = content.replace("__DIR__.'/config.example.php'", "dirname(__DIR__,2).'/config.example.php'").replace('dbname=server;', 'dbname=server_test;').replace("$db_username = 'staff_server';", "$db_username = 'staff_server_test';").replace("['local_http'=>true,'phpmyadmin_url'=>'http://127.0.0.1:8092/']", "['local_http'=>true,'session_name'=>'STAFFSERVERTEST']")
    (ROOT/'tests/.local/config.php').write_text(testconfig); (ROOT/'tests/.local/config.php').chmod(0o600)
    (ROOT/'tests/.local/access.json').write_text(json.dumps({'username':'admin','password':password})); (ROOT/'tests/.local/access.json').chmod(0o600)
subprocess.run([PHP,str(ROOT/'cli/migrate.php')],cwd=ROOT,check=True)
services = [(8090,'php',config)]
if (ROOT/'tests/.local/config.php').exists():
    env = os.environ.copy(); env['STAFF_SERVER_CONFIG'] = str(ROOT/'tests/.local/config.php')
    subprocess.run([PHP,str(ROOT/'cli/migrate.php')],cwd=ROOT,env=env,check=True)
    services.append((8091,'test-php',ROOT/'tests/.local/config.php'))
for port, filename, cfg in services:
    with socket.socket() as check:
        if check.connect_ex(('127.0.0.1',port)) == 0:
            if not (STATE/(filename+'.pid')).exists(): raise RuntimeError('Port already occupied: '+str(port))
            continue
    sessions = STATE/(filename+'-sessions'); sessions.mkdir(exist_ok=True); sessions.chmod(0o700)
    env = os.environ.copy(); env['STAFF_SERVER_CONFIG'] = str(cfg)
    with (STATE/(filename+'.log')).open('ab') as log:
        process = subprocess.Popen([PHP,'-d','display_errors=0','-d','opcache.enable=0','-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(ROOT/'public'),str(ROOT/'local/router.php')],cwd=ROOT,env=env,stdout=log,stderr=log,start_new_session=True)
    (STATE/(filename+'.pid')).write_text(str(process.pid))
print('STAFF SERVER: http://127.0.0.1:8090 — credentials: outputs/local-access.txt')
subprocess.run(['python3',str(ROOT/'local/start-phpmyadmin.py')],cwd=ROOT,check=True)
