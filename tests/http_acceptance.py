"""Exercise authentication and protected pages through real HTTP requests."""
import http.cookiejar, json, os, re, urllib.request, urllib.error
from pathlib import Path
base = os.environ.get('TEST_URL', 'http://127.0.0.1:8091')
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(route, data=None):
    from urllib.parse import urlencode
    body = urlencode(data).encode() if data is not None else None
    try:
        with client.open(base + route, body) as response:
            return response.status, response.read().decode(), response.geturl()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.geturl()
def csrf(html):
    found = re.search(r'name="csrf" value="([a-f0-9]+)"', html)
    assert found, 'CSRF token absent'
    return found.group(1)
status, html, url = request('/?route=dashboard')
assert status == 200 and 'route=login' in url, 'Protected dashboard must redirect to login'
status, _, url = request('/?route=map')
assert 'route=login' in url, 'Map must be protected'
assert request('/?route=login', {'username':'admin','password':'wrong','csrf':'bad'})[0] == 403, 'Invalid CSRF must fail'
status, html, _ = request('/?route=login')
token = csrf(html)
status, html, _ = request('/?route=login', {'username':'admin','password':'wrong','csrf':token})
assert status == 200 and 'name="password"' in html, 'Wrong password must retain login'
credentials = json.loads(Path('tests/.local/access.json').read_text())
session_before = [(cookie.name, cookie.value) for cookie in jar]
status, html, url = request('/?route=login', {'username':credentials['username'],'password':credentials['password'],'csrf':csrf(html)})
assert status == 200 and 'route=dashboard' in url and 'id="crm-table"' in html, 'Correct password must open dashboard'
assert session_before != [(cookie.name, cookie.value) for cookie in jar], 'Authentication must rotate the session ID'
assert '<script>alert(1)</script>' not in html and '&lt;script&gt;' in html, 'Stored church name must be escaped'
assert request('/?route=dashboard&period=2026-99')[0] == 400, 'Invalid period must fail'
assert request('/?route=installation&uuid=missing')[0] == 404, 'Missing installation must be 404'
status, data, _ = request('/?route=map')
points = json.loads(data)['points']
assert any(point['lat'] == 0 and point['lng'] == 0 for point in points), 'Zero coordinate marker absent'
assert not any(point['name'] == 'No location' for point in points), 'Missing location got fake marker'
status, detail, _ = request('/?route=installation&uuid=11111111-1111-4111-8111-111111111111')
assert status == 200 and '2026-09' in detail and '&lt;script&gt;' in detail, 'Detail and historical snapshots missing'
assert '<dd>Отримано</dd>' in detail, 'A stored snapshot must count as received even without an attempt log'
assert request('/?route=logout')[0] == 405, 'Logout must use POST'
assert request('/?route=logout', {'csrf':'bad'})[0] == 403, 'Logout CSRF must fail'
request('/?route=logout', {'csrf':csrf(html)})
assert 'route=login' in request('/?route=dashboard')[2], 'Logout must clear authentication'
# Shorten only the isolated test session lifetime and restore config afterwards.
import time
test_config = Path('tests/.local/config.php')
original_config = test_config.read_text()
try:
    test_config.write_text(original_config.replace("'session_name'=>'STAFFSERVERTEST'", "'session_name'=>'STAFFSERVERTEST','session_timeout'=>1"))
    _, login, _ = request('/?route=login')
    request('/?route=login', {'username':credentials['username'],'password':credentials['password'],'csrf':csrf(login)})
    time.sleep(2.1)
    assert 'route=login' in request('/?route=dashboard')[2], 'Expired session must not grant access'
finally:
    test_config.write_text(original_config)
for _ in range(8):
    _, login, _ = request('/?route=login')
    result = request('/?route=login', {'username':credentials['username'],'password':'wrong','csrf':csrf(login)})
assert result[0] == 429, 'Persistent login limit missing'
print('PASS: HTTP authentication, CSRF, pages, escaping, map, period validation, logout, rate limit')
