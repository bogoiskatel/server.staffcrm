#!/usr/bin/env python3
"""Stop only the local processes belonging to this project."""
import os, signal, subprocess
from pathlib import Path
root=Path(__file__).resolve().parent.parent
state=root/'work/runtime'
for name in ['php','test-php']:
    pidfile=state/(name+'.pid')
    if not pidfile.exists(): continue
    pid=int(pidfile.read_text())
    command=subprocess.run(['ps','-p',str(pid),'-o','command='],capture_output=True,text=True).stdout
    if str(root/'local/router.php') in command:
        os.kill(pid,signal.SIGTERM)
    pidfile.unlink()
pidfile=state/'phpmyadmin.pid'
if pidfile.exists():
    pid=int(pidfile.read_text())
    command=subprocess.run(['ps','-p',str(pid),'-o','command='],capture_output=True,text=True).stdout
    if str(state/'phpmyadmin') in command:
        os.kill(pid,signal.SIGTERM)
    pidfile.unlink()
proxy=root/'work/integration/tls-proxy.pid'
if proxy.exists():
    pid=int(proxy.read_text())
    command=subprocess.run(['ps','-p',str(pid),'-o','command='],capture_output=True,text=True).stdout
    if str(root/'local/crm-tls-proxy.py') in command: os.kill(pid,signal.SIGTERM)
    proxy.unlink()
subprocess.run(['/opt/homebrew/opt/mariadb/bin/mariadb-admin','--no-defaults','--socket='+str(state/'mysql.sock'),'-u','root','shutdown'],capture_output=True)
print('STAFF SERVER local services stopped; database files retained.')
