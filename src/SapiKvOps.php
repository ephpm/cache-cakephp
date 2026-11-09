<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake;

use RuntimeException;

/**
 * {@see KvOpsInterface} backed by ePHPm's native `ephpm_kv_*` functions.
 *
 * These functions exist ONLY inside a running ePHPm server process (the HTTP
 * SAPI). They are not available under the `ephpm php` CLI, where a cache build
 * must instead reach a running server over its RESP listener. The constructor
 * fails fast if the API is missing so a misconfiguration surfaces immediately
 * rather than silently no-opping.
 */
final class SapiKvOps implements KvOpsInterface
{
    public function __construct()
    {
        if (!function_exists('ephpm_kv_get')) {
            throw new RuntimeException(
                'ePHPm native KV functions are unavailable. The ephpm_kv_* API exists only '
                . 'inside a running ePHPm server process, not under the `ephpm php` CLI or a '
                . 'non-ePHPm SAPI. Use a different cache engine there.'
            );
        }
    }

    public function get(string $key): ?string
    {
        $value = ephpm_kv_get($key);

        return $value === null ? null : (string)$value;
    }

    public function set(string $key, string $value, int $ttl): bool
    {
        return (bool)ephpm_kv_set($key, $value, $ttl);
    }

    public function setNx(string $key, string $value, int $ttl): bool
    {
        return (bool)ephpm_kv_setnx($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return (int)ephpm_kv_del($key) > 0;
    }

    public function exists(string $key): bool
    {
        return (bool)ephpm_kv_exists($key);
    }

    public function incrBy(string $key, int $offset): int|false
    {
        $result = ephpm_kv_incr_by($key, $offset);

        return $result === false ? false : (int)$result;
    }

    public function expire(string $key, int $ttl): bool
    {
        return (bool)ephpm_kv_expire($key, $ttl);
    }

    public function flushAll(): bool
    {
        return (bool)ephpm_kv_flush_all();
    }
}
