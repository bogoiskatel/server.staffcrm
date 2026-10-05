"""Check report controls and validation through authenticated HTTP on server_test."""
import http.cookiejar,json,re,urllib.request,urllib.error,urllib.parse
from pathlib import Path
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
base='http://127.0.0.1:8091'
def req(path,data=None):
    try:
        with client.open(base+path,urllib.parse.urlencode(data).encode() if data else None) as r:return r.status,r.read().decode()
    except urllib.error.HTTPError as e:return e.code,e.read().decode()
_,html=req('/?route=login');csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
credentials=json.loads(Path('tests/.local/access.json').read_text());assert req('/?route=login',dict(credentials,csrf=csrf))[0]==200
for query in ['report=all','report=year&year=2026','report=month&period=2026-09','report=month&period=current','report=range&date_start=2026-10-01&date_end=2026-10-01']:
    status,html=req('/?route=dashboard&tab=network&'+query);assert status==200 and 'id="report-mode"' in html
    assert html.index('id="network-panel"')<html.index('id="report-mode"')<html.index('id="statistics-panel"')
for query in ['report=unknown','report=year&year=bad','report=range&date_start=2026-02-31&date_end=2026-03-01','report=range&date_start=2026-10-05&date_end=2026-10-01']:
    assert req('/?route=dashboard&'+query)[0]==400
_,html=req('/?route=dashboard&tab=statistics&report=year&year=2025');registry=html.split('id="statistics-panel"')[1].split('id="orders-panel"')[0]
assert '999' in registry and '12' in registry,'Registry should retain latest data independent of network filter'
print('PASS: report modes, placement under Network, invalid date/year requests, independent registry')
