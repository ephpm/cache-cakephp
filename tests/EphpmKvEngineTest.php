<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake\Test;

use Ephpm\Cache\Cake\EphpmKvEngine;
use Ephpm\Cache\Cake\InMemoryKvOps;
use PHPUnit\Framework\TestCase;

final class EphpmKvEngineTest extends TestCase
{
    private InMemoryKvOps $ops;
    private EphpmKvEngine $cache;

    protected function setUp(): void
    {
        $this->ops = new InMemoryKvOps();
        $this->cache = new EphpmKvEngine();
        $this->assertTrue($this->cache->init(['ops' => $this->ops, 'prefix' => 'test_', 'duration' => 60]));
    }

    public function testSetGetString(): void
    {
        $this->assertTrue($this->cache->set('greeting', 'hello'));
        $this->assertSame('hello', $this->cache->get('greeting'));
    }

    public function testSetGetArray(): void
    {
        $value = ['a' => 1, 'b' => ['c' => true], 'list' => [1, 2, 3]];
        $this->assertTrue($this->cache->set('payload', $value));
        $this->assertSame($value, $this->cache->get('payload'));
    }

    public function testMissReturnsDefault(): void
    {
        $this->assertNull($this->cache->get('absent'));
        $this->assertSame('fallback', $this->cache->get('absent', 'fallback'));
    }

    public function testIntegerStoredRawAndReadBackAsInt(): void
    {
        $this->assertTrue($this->cache->set('counter', 41));
        $this->assertSame(41, $this->cache->get('counter'));
        // Stored raw (not serialized) so the KV sees a native integer.
        $this->assertSame('41', $this->ops->get('test_counter'));
    }

    public function testIncrementAndDecrement(): void
    {
        $this->assertTrue($this->cache->set('hits', 10));
        $this->assertSame(15, $this->cache->increment('hits', 5));
        $this->assertSame(13, $this->cache->decrement('hits', 2));
        $this->assertSame(13, $this->cache->get('hits'));
    }

    public function testIncrementMissingKeyInitialisesToOffset(): void
    {
        $this->assertSame(1, $this->cache->increment('fresh'));
        $this->assertSame(3, $this->cache->increment('fresh', 2));
    }

    public function testIncrementNonIntegerReturnsFalse(): void
    {
        $this->cache->set('name', 'luther');
        $this->assertFalse($this->cache->increment('name'));
    }

    public function testAddIsAtomicSetIfAbsent(): void
    {
        $this->assertTrue($this->cache->add('once', 'first'));
        $this->assertFalse($this->cache->add('once', 'second'));
        $this->assertSame('first', $this->cache->get('once'));
    }

    public function testHasAndDelete(): void
    {
        $this->cache->set('temp', 'v');
        $this->assertTrue($this->cache->has('temp'));
        $this->assertTrue($this->cache->delete('temp'));
        $this->assertFalse($this->cache->has('temp'));
        $this->assertFalse($this->cache->delete('temp'));
    }

    public function testClearFlushesEverything(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->assertTrue($this->cache->clear());
        $this->assertNull($this->cache->get('a'));
        $this->assertNull($this->cache->get('b'));
    }

    public function testPrefixIsApplied(): void
    {
        $this->cache->set('k', 'v');
        $this->assertSame('v', $this->ops->get('test_k') !== null ? unserialize($this->ops->get('test_k')) : null);
        $this->assertNull($this->ops->get('k'));
    }

    public function testGroupInvalidationChangesKeyNamespace(): void
    {
        $grouped = new EphpmKvEngine();
        $grouped->init(['ops' => $this->ops, 'prefix' => 'g_', 'groups' => ['posts']]);

        $grouped->set('article-1', 'body');
        $this->assertSame('body', $grouped->get('article-1'));

        // Clearing the group bumps its generation counter; the old entry is no
        // longer reachable even though it still physically exists until TTL.
        $this->assertTrue($grouped->clearGroup('posts'));
        $this->assertNull($grouped->get('article-1'));
    }

    public function testSetMultipleGetMultiple(): void
    {
        $this->assertTrue($this->cache->setMultiple(['x' => 1, 'y' => 'two']));
        $this->assertSame(['x' => 1, 'y' => 'two'], (array)$this->cache->getMultiple(['x', 'y']));
    }

    public function testInitReturnsFalseWithoutNativeFunctionsAndNoInjectedOps(): void
    {
        // On a plain PHP runtime ephpm_kv_* do not exist, so SapiKvOps throws
        // and init() must report the engine unavailable rather than fatal.
        if (function_exists('ephpm_kv_get')) {
            $this->markTestSkipped('Running inside ePHPm; native functions present.');
        }
        $engine = new EphpmKvEngine();
        $this->assertFalse($engine->init([]));
    }
}
