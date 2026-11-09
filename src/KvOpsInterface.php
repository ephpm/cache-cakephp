<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake;

/**
 * Thin seam over ePHPm's native in-process KV functions.
 *
 * Splitting the raw `ephpm_kv_*` calls behind an interface keeps
 * {@see EphpmKvEngine} unit-testable off the ePHPm runtime (via
 * {@see InMemoryKvOps}) and lets the engine degrade gracefully when the
 * native API is absent.
 *
 * Values are opaque strings: serialization is the engine's concern, not the
 * backend's. A TTL of 0 means "no expiry".
 */
interface KvOpsInterface
{
    /** @return string|null The stored string, or null on a miss. */
    public function get(string $key): ?string;

    /** Store a value with a TTL in seconds (0 = no expiry). */
    public function set(string $key, string $value, int $ttl): bool;

    /** Store only if the key is absent (atomic). Returns false if it already exists. */
    public function setNx(string $key, string $value, int $ttl): bool;

    public function delete(string $key): bool;

    public function exists(string $key): bool;

    /**
     * Atomically add $offset to the integer at $key (negative to subtract).
     * A missing key is initialised to $offset; a non-integer value yields false.
     *
     * @return int|false The new value, or false if the value is not an integer.
     */
    public function incrBy(string $key, int $offset): int|false;

    public function expire(string $key, int $ttl): bool;

    /** Flush this tenant's entire KV keyspace. */
    public function flushAll(): bool;
}
