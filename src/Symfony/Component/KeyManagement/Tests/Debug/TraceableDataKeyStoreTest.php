<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\Debug\TraceableDataKeyStore;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;

class TraceableDataKeyStoreTest extends TestCase
{
    private KeyManagementDataCollector $collector;
    private InMemoryDataKeyStore $store;
    private TraceableDataKeyStore $traceable;

    protected function setUp(): void
    {
        $this->collector = new KeyManagementDataCollector();
        $this->store = new InMemoryDataKeyStore();
        $this->traceable = new TraceableDataKeyStore($this->store, $this->collector, 'store');
    }

    public function testTheHandleIsForwardedUntouched()
    {
        $handle = $this->traceable->current('user.email');

        $this->assertSame($handle->reference, $this->store->get($handle->reference)->reference);
        $this->assertFalse($handle->isReleased(), 'wrapping a store must not shorten the life of what it hands out.');
    }

    public function testTheAdministrationHalfIsNotClaimed()
    {
        $this->assertNotInstanceOf(RewrappableDataKeyStoreInterface::class, $this->traceable, 'rewrapping runs from the console, over a store that may not offer it.');
        $this->assertSame($this->store, $this->traceable->getStore(), 'what this one declines stays reachable on the store it decorates.');
    }

    public function testTheScopeAndItsDataKeysAreCollected()
    {
        $handle = $this->traceable->current('user.email');
        $this->traceable->get($handle->reference);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame('user.email', $key['label']);
        $this->assertSame(KeyManagementDataCollector::KIND_SCOPE, $key['kind']);
        $this->assertSame(['current' => 1, 'get' => 1], $key['operations'], 'reading a key of this scope joins the scope rather than opening a row of its own.');
        $this->assertCount(1, $key['data_keys']);
        $this->assertSame(2, $this->collector->getStoreCallCount());
        $this->assertSame(2, $this->collector->getServices()[KeyManagementDataCollector::LAYER_STORE]['store']['ops']);
    }

    public function testAKeyThisRequestNeverCreatedIsCollectedUnderItsReference()
    {
        $reference = $this->store->current('user.email')->reference;

        $this->traceable->get($reference);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame(KeyManagementDataCollector::KIND_DATA_KEY, $key['kind']);
        $this->assertSame(['get' => 1], $key['operations']);
    }

    public function testAFailureIsCollectedAndRethrown()
    {
        try {
            $this->traceable->get('00000000-0000-0000-0000-000000000000');
            $this->fail('The failure must reach the caller.');
        } catch (DataKeyNotFoundException) {
        }

        $this->collector->lateCollect();

        $this->assertSame(DataKeyNotFoundException::class, $this->collector->getKeys()[0]['error']['class']);
        $this->assertSame(1, $this->collector->getErrorCount());
    }
}
