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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\Debug\TraceableEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[RequiresPhpExtension('openssl')]
class TraceableEnvelopeEncrypterTest extends TestCase
{
    private KeyManagementDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new KeyManagementDataCollector();
    }

    public function testTheDecoratedEncrypterStaysReachable()
    {
        $encrypter = new EnvelopeEncrypter(new InMemoryKms());

        $this->assertSame($encrypter, new TraceableEnvelopeEncrypter($encrypter, $this->collector, 'default')->getEncrypter());
    }

    public function testTheRoundTripIsForwardedUntouched()
    {
        $traceable = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter(new InMemoryKms()), $this->collector, 'default');

        $envelope = $traceable->encrypt('app', 'hello', 'user:42');

        $this->assertSame('hello', $traceable->decrypt($envelope, 'user:42'));
    }

    public function testASelfContainedEnvelopeIsCollectedUnderItsMasterKey()
    {
        $traceable = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter(new InMemoryKms()), $this->collector, 'default');
        $envelope = $traceable->encrypt('app', 'hello');
        $traceable->decrypt($envelope);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame('app', $key['label']);
        $this->assertSame(KeyManagementDataCollector::KIND_MASTER_KEY, $key['kind']);
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $key['operations']);
        $this->assertSame([], $key['data_keys'], 'a self-contained envelope carries its data key rather than sharing one.');
        $this->assertSame(2, $this->collector->getEnvelopeCallCount());
    }

    public function testAStoredEnvelopeIsCollectedUnderItsScope()
    {
        $store = new InMemoryDataKeyStore();
        $traceable = new TraceableEnvelopeEncrypter(new StoredEnvelopeEncrypter($store), $this->collector, 'stored');
        $envelope = $traceable->encrypt('user.email', 'jane@example.com');
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame('user.email', $key['label']);
        $this->assertSame(KeyManagementDataCollector::KIND_SCOPE, $key['kind']);
        $this->assertCount(1, $key['data_keys']);
        $this->assertMatchesRegularExpression('/^[[:print:]]+$/', $key['data_keys'][0], 'a reference is opaque bytes, which the panel shows in a printable form.');
    }

    public function testReadingAPayloadJoinsTheScopeThatWroteIt()
    {
        $store = new InMemoryDataKeyStore();
        $traceable = new TraceableEnvelopeEncrypter(new StoredEnvelopeEncrypter($store), $this->collector, 'stored');
        $envelope = $traceable->encrypt('user.email', 'jane@example.com');
        $traceable->decrypt($envelope);
        $this->collector->lateCollect();

        $this->assertCount(1, $this->collector->getKeys(), 'a stored envelope records no scope, so the reference it names is resolved back to one.');
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->collector->getKeys()[0]['operations']);
    }

    public function testAPayloadWrittenBeforeThisRequestIsCollectedUnderItsDataKey()
    {
        $store = new InMemoryDataKeyStore();
        $envelope = new StoredEnvelopeEncrypter($store)->encrypt('user.email', 'jane@example.com');

        $traceable = new TraceableEnvelopeEncrypter(new StoredEnvelopeEncrypter($store), $this->collector, 'stored');
        $traceable->decrypt($envelope);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame(KeyManagementDataCollector::KIND_DATA_KEY, $key['kind'], 'nothing in this request said which scope that key serves.');
        $this->assertMatchesRegularExpression('/^[[:print:]]+$/', $key['label']);
        $this->assertSame(['decrypt' => 1], $key['operations']);
    }

    public function testTheContentsAreNotCollected()
    {
        $traceable = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter(new InMemoryKms()), $this->collector, 'default');
        $envelope = $traceable->encrypt('app', 'correct horse battery staple');
        $traceable->decrypt($envelope);
        $this->collector->lateCollect();

        $collected = serialize($this->collector);

        $this->assertStringNotContainsString('correct horse battery staple', $collected);
        $this->assertStringNotContainsString($envelope->ciphertext, $collected);
    }

    public function testAFailureIsCollectedAndRethrown()
    {
        $traceable = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter(new InMemoryKms()), $this->collector, 'default');
        $envelope = $traceable->encrypt('app', 'hello', 'user:42');

        try {
            $traceable->decrypt(Envelope::fromBytes((string) $envelope), 'user:43');
            $this->fail('The failure must reach the caller.');
        } catch (DecryptionFailedException) {
        }

        $this->collector->lateCollect();

        $this->assertSame(DecryptionFailedException::class, $this->collector->getKeys()[0]['error']['class']);
        $this->assertSame(1, $this->collector->getErrorCount());
    }
}
