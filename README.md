# cache-cakephp

A [CakePHP 5](https://cakephp.org) cache engine backed by **ePHPm's built-in,
in-process KV store** — through ePHPm's native `ephpm_kv_*` PHP functions. No
`phpredis` extension, no TCP socket, no RESP round-trip, no `AUTH`: the KV handle
is already bound to the running tenant's keyspace, so reads and writes are
direct in-process calls.

It is the CakePHP sibling of [`ephpm/cache-wordpress`](https://github.com/ephpm/cache-wordpress),
and the in-process equivalent of pointing CakePHP's stock `RedisEngine` at
ePHPm's RESP listener — but faster and with nothing to install.

## Install

```sh
composer require ephpm/cache-cakephp
```

Until it is on Packagist, add the repo:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ephpm/cache-cakephp" }
  ]
}
```

## Use

Wire it as a cache engine, ideally only when the native API is present, with a
fallback for CLI / non-ePHPm contexts:

```php
use Cake\Cache\Cache;
use Ephpm\Cache\Cake\EphpmKvEngine;

Cache::setConfig('default', function_exists('ephpm_kv_get')
    ? ['className' => EphpmKvEngine::class, 'prefix' => 'myapp_', 'duration' => '+1 hour']
    : ['className' => 'File', 'path' => CACHE]); // CLI / dev fallback
```

Then use `Cache` as usual — `read`, `write`, `add`, `increment`, `decrement`,
`delete`, `clear`, and groups all work.

### Config

Standard CakePHP cache options apply:

| key | meaning |
| --- | --- |
| `duration` | default TTL (seconds, or a strtotime string like `'+1 hour'`). `0` = no expiry |
| `prefix` | key prefix (keep distinct per app/config) |
| `groups` | group tags for `Cache::clearGroup()` |
| `allowedClasses` | `allowed_classes` for `unserialize()` on read — `false` to forbid objects (safe for scalar/array caches), or a list of FQCNs |
| `ops` | (testing) inject a `KvOpsInterface`, e.g. `InMemoryKvOps` |

## How it works

- **Serialization.** Integers are stored as their raw decimal string so
  `increment()`/`decrement()` operate on native integers via `ephpm_kv_incr_by`;
  everything else is PHP-`serialize()`d. (The same trick CakePHP's `RedisEngine`
  uses.) On read, an integer-looking string comes back as an `int`, otherwise
  `unserialize()`.
- **Atomicity.** `add()` maps to `ephpm_kv_setnx` (atomic set-if-absent), not a
  read-then-write.
- **Groups.** `clearGroup()` bumps a per-group generation counter folded into the
  key prefix (CakePHP's standard mechanism), so a group is invalidated without
  enumerating keys. Stale entries age out by TTL.
- **`clear()`** flushes this tenant's entire keyspace via `ephpm_kv_flush_all`.
  ePHPm namespaces the KV per tenant, so it does not touch other sites running on
  the same ePHPm instance.
- **Testability.** The raw calls sit behind `KvOpsInterface`; `SapiKvOps` is the
  real backend and `InMemoryKvOps` is an array fake for unit tests on a plain PHP
  runtime.

## Caveats

- **Serving process only.** `ephpm_kv_*` exist inside the ePHPm HTTP server, not
  under the `ephpm php` CLI. `SapiKvOps` throws if the functions are absent, so
  `EphpmKvEngine::init()` returns `false` there — select the engine conditionally
  (as above) and fall back. For CLI/cron work that must touch the KV, reach a
  running server over its RESP listener (phpredis) instead, or trigger the work
  over HTTP.
- **Tier-1 cache.** The store is an in-process map; entries do **not** survive a
  server restart. Treat it as a cache, never as durable storage.

## Test

```sh
composer install
composer test
```

The suite runs entirely on `InMemoryKvOps`, so it needs neither ePHPm nor
`phpredis`.

## License

MIT.
