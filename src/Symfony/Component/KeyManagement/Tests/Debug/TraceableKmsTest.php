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
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Debug\TraceableDataKeyGenerator;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;

class TraceableKmsTest extends TestCase
{
    private KeyManagementDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new KeyManagementDataCollector(['default']);
    }

    public function testTheDataKeyCapabilityIsMirrored()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');

        $this->assertInstanceOf(TraceableDataKeyGenerator::class, $traceable);
        $this->assertInstanceOf(DataKeyGeneratorInterface::class, $traceable);
    }

    public function testTheDataKeyCapabilityIsNotInventedOverABackendDecliningIt()
    {
        $traceable = TraceableKms::wrap(new EncryptOnlyKms(), $this->collector, 'default');

        $this->assertNotInstanceOf(DataKeyGeneratorInterface::class, $traceable, 'the console commands and the Doctrine store detect that capability with instanceof.');
        $this->assertInstanceOf(EncrypterInterface::class, $traceable);
        $this->assertInstanceOf(DecrypterInterface::class, $traceable);
    }

    public function testTheDecoratedBackendStaysReachable()
    {
        $kms = new InMemoryKms();

        $this->assertSame($kms, TraceableKms::wrap($kms, $this->collector, 'default')->getKms(), 'an application naming the backend it talks to would otherwise read the decorator.');
    }

    public function testTheRoundTripIsForwardedUntouched()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');

        $ciphertext = $traceable->encrypt('app', 'hello', 'user:42');

        $this->assertSame('hello', $traceable->decrypt($ciphertext, 'user:42'));
    }

    public function testEncryptionIsCollected()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');
        $traceable->encrypt('app', 'hello', 'user:42', true);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame('app', $key['label']);
        $this->assertSame(KeyManagementDataCollector::KIND_MASTER_KEY, $key['kind']);
        $this->assertSame(['encrypt' => 1], $key['operations']);
        $this->assertSame([InMemoryKms::class], $key['backends']);
        $this->assertSame(1, $key['aad']);
        $this->assertSame(1, $key['deterministic']);
        $this->assertSame(5, $key['in']);
        $this->assertGreaterThan(0, $key['out']);

        $service = $this->collector->getServices()[KeyManagementDataCollector::LAYER_KMS]['default'];

        $this->assertSame(1, $service['ops']);
        $this->assertSame(1, $this->collector->getKmsCallCount());
        $this->assertSame(5, $this->collector->getBytesIn());
    }

    public function testTheCallSiteIsCollected()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');
        $traceable->encrypt('app', 'hello');
        $this->collector->lateCollect();

        $caller = $this->collector->getCallers()[0];

        $this->assertSame(__FILE__, $caller['file']);
        $this->assertSame('TraceableKmsTest.php', $caller['name']);
        $this->assertSame(['app'], $caller['keys']);
        $this->assertSame(1, $caller['layers'][KeyManagementDataCollector::LAYER_KMS]);
    }

    public function testDataKeyGenerationIsCollected()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');
        $dataKey = $traceable->generateDataKey('app', 16);
        $traceable->unwrapDataKey($dataKey->wrapped);
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame('app', $key['label']);
        $this->assertSame(['generate_data_key' => 1, 'unwrap_data_key' => 1], $key['operations']);
        $this->assertSame(2, $this->collector->getKmsCallCount());
    }

    /**
     * The panel exists to count round trips, not to leak what they carried.
     */
    public function testNeitherThePlaintextNorTheCiphertextIsCollected()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');
        $ciphertext = $traceable->encrypt('app', 'correct horse battery staple');
        $traceable->decrypt($ciphertext);
        $this->collector->lateCollect();

        $collected = serialize($this->collector);

        $this->assertStringNotContainsString('correct horse battery staple', $collected);
        $this->assertStringNotContainsString($ciphertext->blob, $collected);
    }

    public function testTheAadIsCountedAndNotKept()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');
        $traceable->encrypt('app', 'hello', 'tenant:acme');
        $traceable->encrypt('app', 'hello');
        $this->collector->lateCollect();

        $key = $this->collector->getKeys()[0];

        $this->assertSame(1, $key['aad'], 'one of the two operations bound an AAD.');
        $this->assertStringNotContainsString('tenant:acme', serialize($this->collector), 'an AAD is not a secret, but it carries identifiers the panel has no reason to keep.');
    }

    public function testAFailureIsCollectedAndRethrown()
    {
        $traceable = TraceableKms::wrap(new InMemoryKms(), $this->collector, 'default');

        try {
            $traceable->decrypt(new Ciphertext('not-a-ciphertext', 'app'));
            $this->fail('The failure must reach the caller.');
        } catch (DecryptionFailedException) {
        }

        $this->collector->lateCollect();
        $key = $this->collector->getKeys()[0];

        $this->assertSame(1, $key['errors']);
        $this->assertSame(DecryptionFailedException::class, $key['error']['class']);
        $this->assertSame(1, $this->collector->getErrorCount());
    }
}
