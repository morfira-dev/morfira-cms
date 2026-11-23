#!/usr/bin/env python3
"""Print the warm HTTP benchmark summary as Markdown."""

import argparse
import json
from pathlib import Path
import re


UNITS = {'B': 1, 'kB': 1000, 'MB': 1000**2, 'GB': 1000**3, 'KiB': 1024, 'MiB': 1024**2, 'GiB': 1024**3}


def peak_memory(path):
    peaks = {}
    for line in path.read_text(encoding='utf-8').splitlines():
        line = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', line).strip()
        if not line:
            continue
        sample = json.loads(line)
        match = re.fullmatch(r'([\d.]+)\s*([a-zA-Z]+)', sample['MemUsage'].split('/')[0].strip())
        if match is None:
            raise ValueError(f'Unknown Docker memory sample in {path}')
        mib = float(match[1]) * UNITS[match[2]] / 1024**2
        name = sample['Name']
        peaks[name] = max(peaks.get(name, 0), mib)
    names = path.with_suffix('.containers').read_text(encoding='utf-8').splitlines()
    if len(names) != 2 or peaks.keys() != set(names):
        raise ValueError(f'Expected app and database samples in {path}')
    return ' / '.join(f'{peaks[name]:.1f}' for name in names)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', type=Path, help='artifact directory produced by run-suite.sh')
    root = parser.parse_args().directory
    print('| Platform | Page | Concurrency | TTFB p50 / p95, ms | HTTP 200/s | Errors | HTML bytes | App / DB peak, MiB |')
    print('|---|---|---:|---:|---:|---:|---:|---:|')
    for path in ('rehearsal-about', 'rehearsal-guide'):
        for concurrency in (1, 8):
            for platform in ('morfira', 'wordpress'):
                stem = f'{platform}-{path}-c{concurrency}'
                result = json.loads((root / f'{stem}.json').read_text(encoding='utf-8'))
                if not result['passed']:
                    raise ValueError(f'The benchmark did not pass: {stem}')
                summary = result['summary']
                ttfb = summary['ttfb_ms']
                sizes = summary['response_bytes']
                html_bytes = str(sizes['min']) if sizes['min'] == sizes['max'] else f"{sizes['min']}–{sizes['max']}"
                memory = peak_memory(root / f'{stem}.memory.ndjson')
                print(
                    f"| {platform} | {path} | {concurrency} | {ttfb['p50']:.2f} / {ttfb['p95']:.2f} | "
                    f"{summary['requests_per_second']:.1f} | {summary['errors']} | {html_bytes} | {memory} |",
                )


if __name__ == '__main__':
    main()
