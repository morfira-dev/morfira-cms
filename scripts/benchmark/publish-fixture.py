#!/usr/bin/env python3
"""Seed a fresh, disposable Morfira installation with the WordPress benchmark pages."""

import argparse
import http.cookiejar
import json
from pathlib import Path
import secrets
import subprocess
from urllib.parse import urlsplit
from urllib.request import HTTPRedirectHandler, HTTPCookieProcessor, Request, build_opener


class NoRedirects(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def docker(*arguments):
    return subprocess.run(['docker', *arguments], check=True, stdout=subprocess.PIPE).stdout


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--origin', required=True, help='HTTP loopback origin of the disposable installation')
    parser.add_argument('--container', required=True, help='Running Morfira application container')
    parser.add_argument('--fixture', required=True, type=Path, help='WordPress rehearsal output directory')
    args = parser.parse_args()

    origin = args.origin.rstrip('/')
    url = urlsplit(origin)
    if (url.scheme != 'http' or url.hostname not in {'localhost', '127.0.0.1', '::1'}
            or url.username is not None or url.path or url.query or url.fragment):
        parser.error('--origin must be an HTTP loopback origin')
    export = args.fixture / 'wordpress-export.xml'
    uploads = args.fixture / 'uploads'
    if not export.is_file() or not uploads.is_dir():
        parser.error('--fixture must contain wordpress-export.xml and uploads/')
    bindings = json.loads(docker(
        'inspect', '--format', '{{json (index .NetworkSettings.Ports "8080/tcp")}}', args.container,
    )) or []
    if not any(binding['HostPort'] == str(url.port or 80) for binding in bindings):
        parser.error('--origin must use the published HTTP port of --container')

    client = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirects())

    def api(path, payload=None, token=None):
        request = Request(origin + path)
        if payload is not None:
            request.data = json.dumps(payload).encode()
            request.add_header('Content-Type', 'application/json')
        if token is not None:
            request.add_header('X-CSRF-Token', token)
        with client.open(request, timeout=20) as response:
            data = response.read()
            return json.loads(data) if data else None

    def csrf():
        return api('/api/v1/auth/csrf')['token']

    if api('/api/v1/setup')['required'] is not True:
        parser.error('Morfira must be a fresh installation in the disposable benchmark project')

    owner = {'email': 'benchmark-owner@example.test', 'password': secrets.token_urlsafe(32)}
    api('/api/v1/setup', owner, csrf())
    api('/api/v1/auth/login', owner, csrf())
    token = csrf()

    staging = '/tmp/morfira-html-benchmark-fixture'
    docker('exec', args.container, 'mkdir', '-p', staging)
    docker('cp', str(export.resolve()), f'{args.container}:{staging}/wordpress-export.xml')
    docker('cp', str(uploads.resolve()), f'{args.container}:{staging}/uploads')
    report = json.loads(docker(
        'exec', '-w', '/app/back', args.container, 'php', 'bin/cms', 'cms:import:wordpress',
        f'{staging}/wordpress-export.xml', '--source=html-benchmark', f'--media-dir={staging}/uploads',
        '--apply', '--record-history',
    ))
    paths = {'/rehearsal-about', '/rehearsal-guide'}
    targets = {item['targetPath']: item for item in report['items'] if item['targetPath'] in paths}
    if targets.keys() != paths or any(item['state'] != 'imported' for item in targets.values()):
        raise RuntimeError('Expected both benchmark pages to be imported as drafts')
    for path, item in sorted(targets.items()):
        draft_path = f'/api/v1/admin/pages/{item["draft"]["id"]}'
        draft = api(draft_path)
        api(draft_path + '/publish', {'version': draft['version']}, token)
        print(origin + path)


if __name__ == '__main__':
    main()
