"""Verify order storage through real HTTP, isolated database and private test credentials."""
import http.cookiejar,json,re,subprocess,urllib.request,urllib.error,urllib.parse
from pathlib import Path
php='/opt/homebrew/opt/php@7.4/bin/php'
config=json.loads(subprocess.check_output([php,'-r','echo json_encode(require "tests/.local/config.php");'],text=True))
assert 'dbname=server_test;' in config['db_dsn']
subprocess.run([php,'-r','putenv("STAFF_SERVER_CONFIG=tests/.local/config.php");require "app/bootstrap.php";database(configuration())->exec("DELETE FROM login_attempts");'],check=True)
base='http://127.0.0.1:8091'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,data=None,bearer=None):
    headers={}
    if bearer is not None:headers={'Authorization':'Bearer '+bearer,'Content-Type':'application/json'};body=json.dumps(data).encode()
    else:body=urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with client.open(urllib.request.Request(base+path,body,headers=headers)) as r:return r.status,r.read().decode(),r.url
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
payload={'source_ref':'tests.orders.http','contact':'Request <script>alert(1)</script>','email':'fixture@example.com','phone':'00123','city':'City','church':'Fixture church','members':'50_150','message':'<script>bad</script>','ip':'127.0.0.1','order_date':'2026-10-02'}
assert call('/?route=orders-receive',payload,'wrong')[0]==401
assert call('/?route=orders-receive',{'contact':'bad'},config['orders_webhook_key'])[0]==400
status,body,_=call('/?route=orders-receive',payload,config['orders_webhook_key']);assert status==200
id=json.loads(body)['id'];assert json.loads(call('/?route=orders-receive',payload,config['orders_webhook_key'])[1])['id']==id
assert 'route=login' in call('/?route=dashboard&tab=orders')[2]
_,html,_=call('/?route=login');token=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
credentials=json.loads(Path('tests/.local/access.json').read_text());call('/?route=login',dict(credentials,csrf=token))
status,html,_=call('/?route=dashboard&tab=orders');assert status==200 and 'id="orders-table"' in html
assert 'Request &lt;script&gt;' in html and '<script>alert(1)</script>' not in html
assert all('value="'+s+'"' in html for s in ['waiting','issued','paid','installed'])
token=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
version=re.search(r'name="version" value="([0-9]+)"',html)[1]
update={'csrf':token,'id':str(id),'version':version,'status':'paid','comment':'Note <script>plain text</script>'}
assert call('/?route=order-save',dict(update,csrf='bad'))[0]==403
assert call('/?route=order-save',dict(update,status='forged'))[0]==400
status,html,_=call('/?route=order-save',update);assert status==200 and 'value="paid" selected' in html and 'Note &lt;script&gt;' in html
assert call('/?route=order-save',update)[0]==409
assert json.loads(call('/?route=orders-receive',payload,config['orders_webhook_key'])[1])['id']==id
html=call('/?route=dashboard&tab=orders')[1];assert 'value="paid" selected' in html and 'Note &lt;script&gt;' in html
subprocess.run([php,'-r','putenv("STAFF_SERVER_CONFIG=tests/.local/config.php");require "app/bootstrap.php";$d=database(configuration());$d->prepare("DELETE FROM demo_orders WHERE source_ref=?")->execute(["tests.orders.http"]);'],check=True)
print('PASS: authenticated order editing, webhook auth/dedup, CSRF, status validation, stored XSS, stale-note protection')
