<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake;

use Cake\Cache\CacheEngine;
use DateInterval;
use RuntimeException;

/**
 * CakePHP cache engine backed by ePHPm's built-in, in-process KV store.
 *
 * ePHPm exposes its KV as native PHP functions (`ephpm_kv_get`, `ephpm_kv_set`,
 * ...) inside the serving process — no phpredis extension, no socket, no RESP
 * round trip, no AUTH (the handle is already bound to the tenant's keyspace).
 * This engine is the in-process equivalent of pointing CakePHP's RedisEngine at
 * ePHPm's RESP listener, but faster and with no extension requirement.
 *
 * Caveats worth knowing:
 *  - The native API is available only in the HTTP serving process, NOT the
 *    `ephpm php` CLI. In CLI contexts (e.g. a cron build) use a RESP-based
 *    engine, or trigger the work over HTTP. {@see SapiKvOps} throws if the
 *    functions are absent, so `init()` returns false there — select this engine
 *    conditionally (`function_exists('ephpm_kv_get')`) and fall back otherwise.
 *  - The store is an in-process tier-1 cache: entries do not survive a server
 *    restart. Treat it as a cache, not durable storage.
 *  - `clear()` flushes the whole tenant keyspace (ePHPm namespaces the KV per
 *    tenant, so it does not touch other sites on the same instance).
 *
 * Integers are stored as their raw decimal string so `increment()`/`decrement()`
 * operate on native integers; every other value is PHP-`serialize()`d. This is
 * the same trick CakePHP's RedisEngine uses.
 */
class EphpmKvEngine extends CacheEngine
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'duration' => 3600,
        'groups' => [],
        'prefix' => 'cake_',
        'warnOnWriteFailures' => true,
        // Controls allowed_classes passed to unserialize() on read. false is
        // safest when only scalars/arrays are cached; or pass a list of FQCNs.
        'allowedClasses' => true,
        // Inject a KvOpsInterface (tests pass InMemoryKvOps). Null => SapiKvOps.
        'ops' => null,
    ];

    protected KvOpsInterface $ops;

    /**
     * @param array<string, mixed> $config
     */
    public function init(array $config = []): bool
    {
        parent::init($config);

        $ops = $this->_config['ops'] ?? null;
        if ($ops instanceof KvOpsInterface) {
            $this->ops = $ops;

            return true;
        }

        try {
            $this->ops = new SapiKvOps();
        } catch (RuntimeException) {
            // Not inside an ePHPm server process — let the caller fall back.
            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->ops->set($this->_key($key), $this->serialize($value), $this->duration($ttl));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->ops->get($this->_key($key));
        if ($value === null) {
            return $default;
        }

        return $this->unserialize($value);
    }

    public function has(string $key): bool
    {
        return $this->ops->exists($this->_key($key));
    }

    public function add(string $key, mixed $value): bool
    {
        return $this->ops->setNx($this->_key($key), $this->serialize($value), (int)$this->_config['duration']);
    }

    public function increment(string $key, int $offset = 1): int|false
    {
        return $this->changeBy($key, $offset);
    }

    public function decrement(string $key, int $offset = 1): int|false
    {
        return $this->changeBy($key, -$offset);
    }

    public function delete(string $key): bool
    {
        return $this->ops->delete($this->_key($key));
    }

    public function clear(): bool
    {
        return $this->ops->flushAll();
    }

    /**
     * @return array<string>
     */
    public function groups(): array
    {
        $result = [];
        foreach ($this->_config['groups'] as $group) {
            $counterKey = $this->_config['prefix'] . $group;
            $value = $this->ops->get($counterKey);
            if ($value === null) {
                $value = '1';
                $this->ops->set($counterKey, $value, 0);
            }
            $result[] = $group . $value;
        }

        return $result;
    }

    public function clearGroup(string $group): bool
    {
        // Bump the group generation counter; every key folds the counter into
        // its prefix (see CacheEngine::_key), so old entries become unreachable
        // and age out by TTL. No key enumeration needed.
        return $this->ops->incrBy($this->_config['prefix'] . $group, 1) !== false;
    }

    private function changeBy(string $key, int $offset): int|false
    {
        $prefixed = $this->_key($key);
        $value = $this->ops->incrBy($prefixed, $offset);
        if ($value === false) {
            return false;
        }

        $duration = (int)$this->_config['duration'];
        if ($duration > 0) {
            $this->ops->expire($prefixed, $duration);
        }

        return $value;
    }

    /**
     * Integers are stored raw so incr/decr work on them; everything else is serialized.
     */
    protected function serialize(mixed $value): string
    {
        if (is_int($value)) {
            return (string)$value;
        }

        return serialize($value);
    }

    protected function unserialize(string $value): mixed
    {
        if (preg_match('/^-?\d+$/', $value)) {
            return (int)$value;
        }

        $allowedClasses = $this->getConfig('allowedClasses');
        if ($allowedClasses !== true) {
            return unserialize($value, ['allowed_classes' => $allowedClasses]);
        }

        return unserialize($value);
    }
}
