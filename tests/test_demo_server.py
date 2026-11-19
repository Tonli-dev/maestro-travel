"""The public demo must never expose the Laravel application beside it."""
from functools import partial
from http.client import HTTPConnection
from http.server import ThreadingHTTPServer
from pathlib import Path
import sys
import threading
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from start import Handler, ROOT


class DemoServerTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(('127.0.0.1', 0), partial(Handler, directory=str(ROOT)))
        cls.worker = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.worker.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.worker.join()

    def status(self, path):
        connection = HTTPConnection('127.0.0.1', self.server.server_port)
        connection.request('HEAD', path)
        response = connection.getresponse()
        status = response.status
        response.read()
        connection.close()
        return status

    def test_public_demo_is_available(self):
        for path in ['/', '/app.js', '/style.css', '/assets/istanbul.jpg']:
            with self.subTest(path=path):
                self.assertEqual(self.status(path), 200)

    def test_backend_hidden_files_and_directory_listings_are_blocked(self):
        for path in ['/server/.env', '/server/%2eenv', '/server/database/database.sqlite',
                     '/server/composer.json', '/server/public/storage/', '/.git/config',
                     '/assets/', '/docs/SETUP.md', '/assets/../server/.env']:
            with self.subTest(path=path):
                self.assertEqual(self.status(path), 404)


if __name__ == '__main__':
    unittest.main()
