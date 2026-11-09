<?php

/**
 * IDE / static-analysis stubs for ePHPm's native KV functions.
 *
 * These functions are provided by the ePHPm runtime inside the serving process;
 * this file is NOT autoloaded and must never be shipped into runtime. It exists
 * only so static analysers and IDEs understand the ephpm_kv_* surface this
 * package calls in SapiKvOps.
 */

if (false) {
    function ephpm_kv_get(string $key): ?string {}
    function ephpm_kv_set(string $key, string $value, int $ttl = 0): bool {}
    function ephpm_kv_setnx(string $key, string $value, int $ttl = 0): bool {}
    function ephpm_kv_del(string $key): int {}
    function ephpm_kv_exists(string $key): bool {}
    function ephpm_kv_incr_by(string $key, int $offset): int|false {}
    function ephpm_kv_expire(string $key, int $ttl): bool {}
    function ephpm_kv_ttl(string $key): int {}
    function ephpm_kv_flush_all(): bool {}
}
