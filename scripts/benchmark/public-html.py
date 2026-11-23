"""Measure anonymous HTTP/1.1 HTML delivery on a local benchmark target."""

import argparse
import ipaddress
import json
import math
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlsplit


WRITE_OUT = (
    "%{urlnum}\t%{http_code}\t%{exitcode}\t%{http_version}\t"
    "%{time_starttransfer}\t%{time_total}\t%{size_download}\t%header{content-encoding}\n"
)


def positive_int(value):
    number = int(value)
    if number < 1:
        raise argparse.ArgumentTypeError("expected a positive integer")
    return number


def positive_seconds(value):
    number = float(value)
    if not math.isfinite(number) or number <= 0:
        raise argparse.ArgumentTypeError("expected a finite positive duration")
    return number


def loopback_url(value):
    error = argparse.ArgumentTypeError("expected an HTTP URL with a loopback host and no credentials or fragment")
    if any(ord(character) <= 32 or ord(character) == 127 for character in value):
        raise error
    try:
        url = urlsplit(value)
        if (url.scheme != "http" or not url.hostname or url.username is not None
                or "#" in value or "\\" in value or url.port == 0):
            raise error
        if url.hostname == "localhost" or ipaddress.ip_address(url.hostname).is_loopback:
            return value
    except ValueError as exception:
        raise error from exception
    raise error


def capture(url, requests, concurrency, timeout):
    command = [
        "curl", "-q", "--silent", "--show-error", "--globoff", "--noproxy", "*", "--proto", "=http",
        "--http1.1", "--header", "Accept-Encoding: identity", "--max-time", str(timeout),
        "--parallel", "--parallel-immediate", "--parallel-max", str(concurrency), "--write-out", WRITE_OUT,
    ]
    origin = urlsplit(url)
    if origin.hostname == "localhost":
        command.extend(["--resolve", f"localhost:{origin.port or 80}:127.0.0.1"])
    # Pass repeated URLs through stdin so a long run stays below the system argument limit.
    config = f"url = {json.dumps(url, ensure_ascii=False)}\noutput = {json.dumps(os.devnull)}\n" * requests
    started = time.perf_counter()
    completed = subprocess.run([*command, "--config", "-"], input=config, capture_output=True, text=True)
    wall_seconds = time.perf_counter() - started
    samples = []
    sample_indexes = set()
    for line in completed.stdout.splitlines():
        index, status, exit_code, protocol, ttfb, total, size, encoding = line.split("\t", maxsplit=7)
        index = int(index)
        if index < 0 or index >= requests or index in sample_indexes:
            raise ValueError("curl returned an unexpected or duplicate transfer index")
        sample_indexes.add(index)
        samples.append({
            "index": index,
            "status": int(status),
            "curl_exit_code": int(exit_code),
            "http_version": protocol,
            "ttfb_ms": round(float(ttfb) * 1_000, 6),
            "total_ms": round(float(total) * 1_000, 6),
            "response_bytes": int(size),
            "content_encoding": encoding.strip().lower(),
        })
    return {
        "requested": requests,
        "wall_seconds": wall_seconds,
        "curl_return_code": completed.returncode,
        "curl_stderr": completed.stderr.strip(),
        "samples": samples,
    }


def percentiles(values):
    if not values:
        return {"p50": None, "p95": None}
    ordered = sorted(values)
    return {f"p{percent}": ordered[math.ceil(percent * len(ordered) / 100) - 1] for percent in (50, 95)}


def summarize(batches):
    requested = sum(batch["requested"] for batch in batches)
    wall = sum(batch["wall_seconds"] for batch in batches)
    samples = [sample for batch in batches for sample in batch["samples"]]
    successes = [sample for sample in samples if sample["status"] == 200
                 and sample["curl_exit_code"] == 0 and sample["content_encoding"] in {"", "identity"}]
    sizes = [sample["response_bytes"] for sample in successes]
    return {
        "requests": requested,
        "successes": len(successes),
        "errors": requested - len(successes),
        "missing_samples": requested - len(samples),
        "wall_seconds": round(wall, 6),
        "requests_per_second": round(len(successes) / wall, 3),
        "ttfb_ms": percentiles([sample["ttfb_ms"] for sample in successes]),
        "total_ms": percentiles([sample["total_ms"] for sample in successes]),
        "response_bytes": {"min": min(sizes, default=None), "max": max(sizes, default=None)},
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("url", type=loopback_url)
    parser.add_argument("--output", required=True, type=Path, help="write the JSON artifact to this file")
    parser.add_argument("--requests", type=positive_int, help="requests per repeat, defaults to 200 at c1 or 600 above c1")
    parser.add_argument("--concurrency", type=positive_int, default=1)
    parser.add_argument("--warmup", type=int, default=20, help="unmeasured requests before each repeat; zero skips warmup")
    parser.add_argument("--repeats", type=positive_int, default=3)
    parser.add_argument("--timeout", type=positive_seconds, default=10.0, help="curl transfer timeout in seconds")
    args = parser.parse_args()
    requests = args.requests if args.requests is not None else (200 if args.concurrency == 1 else 600)
    if args.concurrency > requests:
        parser.error("concurrency must not exceed requests per repeat")
    if args.warmup < 0:
        parser.error("warmup must not be negative")

    runs = []
    measured = []
    ok = True
    try:
        curl_version = subprocess.run(["curl", "--version"], check=True, capture_output=True, text=True).stdout.splitlines()[0]
        args.output.parent.mkdir(parents=True, exist_ok=True)
        for iteration in range(1, args.repeats + 1):
            run = {"iteration": iteration, "warmup": None, "measured": None}
            runs.append(run)
            if args.warmup:
                warmup = capture(args.url, args.warmup, min(args.concurrency, args.warmup), args.timeout)
                warmup["metrics"] = summarize([warmup])
                run["warmup"] = warmup
                if warmup["metrics"]["errors"] or warmup["curl_return_code"]:
                    ok = False
                    print(f"Warmup {iteration} failed for {args.url}", file=sys.stderr)
                    break
            batch = capture(args.url, requests, args.concurrency, args.timeout)
            batch["metrics"] = summarize([batch])
            run["measured"] = batch
            measured.append(batch)
            if batch["metrics"]["errors"] or batch["curl_return_code"]:
                ok = False
        result = {
            "url": args.url,
            "curl": curl_version,
            "configuration": {
                "requests": requests,
                "concurrency": args.concurrency,
                "warmup": args.warmup,
                "repeats": args.repeats,
                "timeout_seconds": args.timeout,
                "requested_http_version": "1.1",
                "accept_encoding": "identity",
                "percentile_method": "nearest-rank",
            },
            "passed": ok,
            "summary": summarize(measured) if measured else None,
            "runs": runs,
        }
        args.output.write_text(json.dumps(result, indent=2, allow_nan=False) + "\n", encoding="utf-8")
    except (OSError, subprocess.CalledProcessError, ValueError) as error:
        print(f"Could not run the benchmark: {error}", file=sys.stderr)
        return 2
    print(json.dumps({"passed": ok, "output": str(args.output), "summary": result["summary"]}))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
