# Public HTML benchmark against WordPress

## Scope

The workload measures anonymous public HTML for two pages from the same [WordPress rehearsal fixture](../../scripts/fixtures/wordpress-rehearsal/README.md). The About page contains text, a cross-link and an image; the Guide has text, a heading and a link. Both destinations render the intended text. The standard themes emit different markup and asset references. The request measures the HTML response only. It excludes browser layout, JavaScript, subresource requests and other content delivery surfaces.

Each application and its own MariaDB container have a limit of 1 vCPU and 512 MiB, with no additional swap. Both databases use the same pinned MariaDB image. The HTTP client runs on the host, against loopback, one workload at a time. The requests use HTTP/1.1 and `Accept-Encoding: identity`. The client has no response cache and sends no conditional headers. WordPress uses its bundled theme without an added page or persistent object cache. Morfira uses its bundled theme and published snapshots, with its normal public cache headers. The client records every response status, TTFB, total transfer time and transferred HTML size.

## Reproduce the warm run

Run the following from the Morfira repository root on disposable Compose projects. Keep application or editor activity off those services during sampling. The existing `cms:import:wordpress` command provides the draft references for fixture publication.

```bash
sh scripts/fixtures/wordpress-rehearsal/provision.sh
docker update --cpus 1 --memory 512m --memory-swap 512m \
  morfira-wordpress-rehearsal-wordpress-1 morfira-wordpress-rehearsal-database-1

export CMS_APP_SECRET="$(openssl rand -hex 32)"
export CMS_DATABASE_PASSWORD="$(openssl rand -hex 24)"
export CMS_DATABASE_ROOT_PASSWORD="$(openssl rand -hex 24)"
export CMS_PORT=127.0.0.1:18108 CMS_PUBLIC_SITE_URL=http://localhost:18108
docker compose -p morfira-html-benchmark -f compose.prod.yaml \
  -f scripts/benchmark/compose.yaml up -d --build --wait

python3 scripts/benchmark/publish-fixture.py --origin "$CMS_PUBLIC_SITE_URL" \
  --container morfira-html-benchmark-app-1 --fixture /tmp/morfira-wordpress-rehearsal

results=$(mktemp -d /tmp/morfira-html-benchmark.XXXXXX)
bash scripts/benchmark/run-suite.sh "$results" "$CMS_PUBLIC_SITE_URL" http://localhost:18120 \
  morfira-html-benchmark-app-1 morfira-html-benchmark-database-1 \
  morfira-wordpress-rehearsal-wordpress-1 morfira-wordpress-rehearsal-database-1
python3 scripts/benchmark/summarize.py "$results" > "$results/warm-summary.md"
```

The suite runs three repeats per URL and concurrency. It sends 20 warmup requests before each repeat, then measures 200 requests at concurrency 1 or 600 at concurrency 8. TTFB percentiles use nearest rank across the successful measured transfers. Throughput uses successful responses divided by total measured wall time. Redirects and transfer failures count as errors; a failed workload exits with a nonzero status. The JSON retains the individual transfers and per-repeat summaries. Memory is the highest recorded `docker stats` sample for each service during that workload, including warmup. It is an observed peak at the sampler's interval.

## First request after restart

Run this optional probe after the warm suite. It restarts only the two application containers, leaving each database running. The WordPress fixture restores the same uploads from disk after each restart because its application storage uses tmpfs. The sample starts after readiness and captures the first measured HTML request to the About page. Startup duration and a cold database are outside the measurement. Morfira readiness exercises its separate health route.

```bash
bash scripts/benchmark/run-first-request.sh "$results" "$CMS_PUBLIC_SITE_URL" http://localhost:18120 \
  morfira-html-benchmark-app-1 morfira-wordpress-rehearsal-wordpress-1 \
  /tmp/morfira-wordpress-rehearsal/uploads
```

## Recorded run, 2026-11-23

The run used Morfira `0c202ce` on FrankenPHP with PHP 8.4.28 and WordPress 7.1.5 on Apache 2.4.71 with PHP 8.4.28 and the bundled Twenty Twenty-Five theme. The Morfira run reused an existing application image with the measured backend checkout mounted and prebuilt Admin Studio assets. Docker Desktop reported an arm64 VM with 6 CPUs and about 5.8 GiB RAM. Docker confirmed the same per-container CPU, memory and swap limits on both stacks. Both used MariaDB 11.4.13. Before sampling, each page returned HTTP 200 and contained the expected fixture text.

| Platform | Page | Concurrency | TTFB p50 / p95, ms | HTTP 200/s | Errors | HTML bytes | App / DB peak, MiB |
|---|---|---:|---:|---:|---:|---:|---:|
| morfira | rehearsal-about | 1 | 3.10 / 4.10 | 285.0 | 0 | 5135 | 193.7 / 288.0 |
| wordpress | rehearsal-about | 1 | 29.49 / 36.98 | 31.2 | 0 | 57667 | 375.3 / 343.2 |
| morfira | rehearsal-about | 8 | 11.26 / 46.07 | 441.2 | 0 | 5135 | 196.2 / 288.1 |
| wordpress | rehearsal-about | 8 | 316.39 / 506.67 | 21.3 | 0 | 57667 | 442.4 / 347.2 |
| morfira | rehearsal-guide | 1 | 2.49 / 3.06 | 356.2 | 0 | 4062 | 196.6 / 288.3 |
| wordpress | rehearsal-guide | 1 | 29.15 / 35.29 | 31.9 | 0 | 67744 | 411.3 / 340.8 |
| morfira | rehearsal-guide | 8 | 11.76 / 48.42 | 434.5 | 0 | 4062 | 196.6 / 288.4 |
| wordpress | rehearsal-guide | 8 | 381.68 / 524.69 | 20.9 | 0 | 67744 | 450.3 / 347.9 |

All 9,600 measured warm requests returned HTTP 200. At concurrency 8, Morfira served 435–441 responses/s with a 46–48 ms p95 TTFB. WordPress served about 21 responses/s with a 507–525 ms p95 TTFB. Sampled app memory peaked at 196–197 MiB for Morfira and 442–450 MiB for WordPress at that concurrency. The bundled themes produced substantially different HTML sizes. These results characterize the two configured stacks and their default rendering paths for this fixture.

The three first-request samples for the About page also returned HTTP 200 on both stacks. After application restart and readiness, median TTFB was 55.45 ms for Morfira (54.55–68.87 ms) and 313.66 ms for WordPress (310.59–327.09 ms). The databases stayed running across all six requests.
