#!/usr/bin/env python3
"""Run an isolated copy of installed phpMyAdmin on the STAFF SERVER socket."""
import json, os, secrets, shutil, socket, subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
STATE=ROOT/'work/runtime'
PHP='/opt/homebrew/opt/php@8.2/bin/php'
SOURCE=Path('/opt/homebrew/share/phpmyadmin')
DEST=STATE/'phpmyadmin'
if not SOURCE.is_dir(): raise RuntimeError('Installed phpMyAdmin is missing')
if not DEST.exists(): shutil.copytree(SOURCE,DEST,symlinks=False)
(DEST/'tmp').mkdir(exist_ok=True)
(STATE/'pma-sessions').mkdir(exist_ok=True)
(STATE/'pma-sessions').chmod(0o700)
credentials=STATE/'pma-access.json'
if not credentials.exists():
    password=secrets.token_hex(24)
    command="CREATE USER IF NOT EXISTS 'staff_viewer'@'localhost' IDENTIFIED BY '"+password+"'; GRANT SELECT,SHOW VIEW ON server.* TO 'staff_viewer'@'localhost'; GRANT SELECT,SHOW VIEW ON server_test.* TO 'staff_viewer'@'localhost';"
    subprocess.run(['/opt/homebrew/opt/mariadb/bin/mariadb','--no-defaults','--socket='+str(STATE/'mysql.sock'),'-u','root'],input=command,text=True,capture_output=True,check=True)
    credentials.write_text(json.dumps({'username':'staff_viewer','password':password}));credentials.chmod(0o600)
data=json.loads(credentials.read_text())
config="""<?php
$cfg['blowfish_secret'] = '%s';
$i=1;
$cfg['Servers'][$i]['auth_type']='cookie';
$cfg['Servers'][$i]['host']='localhost';
$cfg['Servers'][$i]['connect_type']='socket';
$cfg['Servers'][$i]['socket']='%s';
$cfg['Servers'][$i]['AllowNoPassword']=false;
$cfg['Servers'][$i]['AllowRoot']=false;
$cfg['Servers'][$i]['only_db']=['server','server_test'];
$cfg['Servers'][$i]['verbose']='STAFF SERVER — local MariaDB';
$cfg['TempDir']=__DIR__.'/tmp';
$cfg['CheckConfigurationPermissions']=false;
$cfg['VersionCheck']=false;
$cfg['LoginCookieValidity']=3600;
$cfg['DefaultLang']='uk';
"""%(secrets.token_hex(16),str(STATE/'mysql.sock'))
cfg=DEST/'config.inc.php'
if not cfg.exists() or 'STAFF SERVER — local MariaDB' not in cfg.read_text(): cfg.write_text(config);cfg.chmod(0o600)
access=ROOT/'outputs/local-access.txt'
text=access.read_text()
if 'phpMyAdmin URL:' not in text:
    access.write_text(text+'\nphpMyAdmin URL: http://127.0.0.1:8092/\nDatabase username: '+data['username']+'\nDatabase password: '+data['password']+'\nAccess: view server and server_test\n')
with socket.socket() as check:
    if check.connect_ex(('127.0.0.1',8092))==0:
        if not (STATE/'phpmyadmin.pid').exists(): raise RuntimeError('Port 8092 is already occupied')
    else:
        with (STATE/'phpmyadmin.log').open('ab') as log:
            process=subprocess.Popen([PHP,'-d','mysqli.default_socket='+str(STATE/'mysql.sock'),'-d','session.save_path='+str(STATE/'pma-sessions'),'-d','display_errors=0','-S','127.0.0.1:8092','-t',str(DEST)],cwd=ROOT,stdout=log,stderr=log,start_new_session=True)
        (STATE/'phpmyadmin.pid').write_text(str(process.pid))
print('phpMyAdmin: http://127.0.0.1:8092/ — credentials: outputs/local-access.txt')
