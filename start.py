"""Local-only preview, with a fixed origin so browser edits persist."""
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from functools import partial
from pathlib import Path
from urllib.request import urlopen
import threading
import webbrowser

ROOT = Path(__file__).resolve().parent
URL = 'http://127.0.0.1:41740/'

class Handler(SimpleHTTPRequestHandler):
    def end_headers(self):
        self.send_header('Cache-Control', 'no-store')
        super().end_headers()

try:
    server = ThreadingHTTPServer(('127.0.0.1', 41740), partial(Handler, directory=str(ROOT)))
except OSError:
    try:
        with urlopen(URL, timeout=2) as response:
            is_demo = b'maestro-travel-demo-v' in urlopen(URL + 'app.js', timeout=2).read()
    except Exception:
        is_demo = False
    if is_demo:
        webbrowser.open(URL)
        print('Maestro demo već je pokrenut. Otvoren je u pregledniku.')
    else:
        print('Adresa 127.0.0.1:41740 je zauzeta. Zatvorite program koji je koristi i pokušajte ponovno.')
    raise SystemExit(0 if is_demo else 1)

print('\nMaestro Travel demo: ' + URL)
print('Ostavite ovaj prozor otvoren tijekom prezentacije. Za zaustavljanje pritisnite Ctrl+C.\n')
threading.Timer(0.5, lambda: webbrowser.open(URL)).start()
try:
    server.serve_forever()
except KeyboardInterrupt:
    print('\nPregled je zaustavljen.')
finally:
    server.server_close()
