<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake;

/**
 * In-memory {@see KvOpsInterface} for unit tests on a plain PHP runtime, where
 * the native `ephpm_kv_*` functions do not exist. It mirrors the semantics the
 * real ePHPm KV was observed to have: a miss returns null, TTL 0 means no
 * expiry, incrBy initialises a missing key to the offset and returns false on a
 * non-integer value.
 */
final class InMemoryKvOps implements KvOpsInterface
{
    /** @var array<string, array{value: string, expires: int|null}> */
    private array $store = [];

    public function get(string $key): ?string
    {
        $this->expireIfDue($key);

        return $this->store[$key]['value'] ?? null;
    }

    public function set(string $key, string $value, int $ttl): bool
    {
        $this->store[$key] = ['value' => $value, 'expires' => $ttl > 0 ? time() + $ttl : null];

        return true;
    }

    public function setNx(string $key, string $value, int $ttl): bool
    {
        $this->expireIfDue($key);
        if (isset($this->store[$key])) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }
        unset($this->store[$key]);

        return true;
    }

    public function exists(string $key): bool
    {
        $this->expireIfDue($key);

        return isset($this->store[$key]);
    }

    public function incrBy(string $key, int $offset): int|false
    {
        $this->expireIfDue($key);
        $current = $this->store[$key]['value'] ?? '0';
        if (!preg_match('/^-?\d+$/', $current)) {
            return false;
        }
        $new = (int)$current + $offset;
        $this->store[$key] = [
            'value' => (string)$new,
            'expires' => $this->store[$key]['expires'] ?? null,
        ];

        return $new;
    }

    public function expire(string $key, int $ttl): bool
    {
        $this->expireIfDue($key);
        if (!isset($this->store[$key])) {
            return false;
        }
        $this->store[$key]['expires'] = $ttl > 0 ? time() + $ttl : null;

        return true;
    }

    public function flushAll(): bool
    {
        $this->store = [];

        return true;
    }

    private function expireIfDue(string $key): void
    {
        $expires = $this->store[$key]['expires'] ?? null;
        if ($expires !== null && $expires <= time()) {
            unset($this->store[$key]);
        }
    }
}
