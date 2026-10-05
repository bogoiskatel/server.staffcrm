#!/usr/bin/env python3
"""Terminate local TLS for the statistics API; never expose the CRM browser UI."""
import http.client
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import ssl

state = Path(__file__).resolve().parents[1] / 'work/integration'
class Handler(BaseHTTPRequestHandler):
    # Forward the fixed read-only API route and bearer header to the loopback backend.
    def do_GET(self):
        if self.path != '/api/':
            self.send_error(404); return
        connection = http.client.HTTPConnection('127.0.0.1', 8765, timeout=20)
        try:
            connection.request('GET', '/api/', headers={'Authorization':self.headers.get('Authorization',''), 'Accept':'application/json'})
            result = connection.getresponse(); body = result.read(262145)
            self.send_response(result.status)
            self.send_header('Content-Type','application/json; charset=utf-8')
            self.send_header('Content-Length',str(len(body)))
            self.send_header('Cache-Control','no-store'); self.end_headers(); self.wfile.write(body)
        except OSError:
            self.send_error(502)
        finally:
            connection.close()
    # Log status only, without bearer headers or payloads.
    def log_message(self, format, *args):
        pass
server = ThreadingHTTPServer(('127.0.0.1',8766), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(state/'tls.crt', state/'tls.key')
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
