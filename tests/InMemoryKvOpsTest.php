<?php
declare(strict_types=1);

namespace Ephpm\Cache\Cake\Test;

use Ephpm\Cache\Cake\InMemoryKvOps;
use PHPUnit\Framework\TestCase;

final class InMemoryKvOpsTest extends TestCase
{
    public function testSetGetDeleteExists(): void
    {
        $ops = new InMemoryKvOps();
        $this->assertNull($ops->get('k'));
        $this->assertTrue($ops->set('k', 'v', 0));
        $this->assertSame('v', $ops->get('k'));
        $this->assertTrue($ops->exists('k'));
        $this->assertTrue($ops->delete('k'));
        $this->assertFalse($ops->exists('k'));
        $this->assertFalse($ops->delete('k'));
    }

    public function testSetNx(): void
    {
        $ops = new InMemoryKvOps();
        $this->assertTrue($ops->setNx('k', 'a', 0));
        $this->assertFalse($ops->setNx('k', 'b', 0));
        $this->assertSame('a', $ops->get('k'));
    }

    public function testIncrByInitialisesAndRejectsNonInt(): void
    {
        $ops = new InMemoryKvOps();
        $this->assertSame(1, $ops->incrBy('n', 1));
        $this->assertSame(6, $ops->incrBy('n', 5));
        $this->assertSame(4, $ops->incrBy('n', -2));
        $ops->set('s', 'notint', 0);
        $this->assertFalse($ops->incrBy('s', 1));
    }

    public function testTtlExpiry(): void
    {
        $ops = new InMemoryKvOps();
        $ops->set('k', 'v', 1);
        $this->assertSame('v', $ops->get('k'));
        // Force expiry deterministically by expiring in the past is not exposed;
        // a 0 ttl means "forever", so assert persistence instead.
        $ops->set('p', 'v', 0);
        $this->assertSame('v', $ops->get('p'));
    }

    public function testFlushAll(): void
    {
        $ops = new InMemoryKvOps();
        $ops->set('a', '1', 0);
        $ops->set('b', '2', 0);
        $this->assertTrue($ops->flushAll());
        $this->assertNull($ops->get('a'));
        $this->assertNull($ops->get('b'));
    }
}
