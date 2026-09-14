<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\DataCollector;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\Debug\TraceableDataKeyStore;
use Symfony\Component\KeyManagement\Debug\TraceableEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\Bridge\RemoteKms;

class KeyManagementDataCollectorTest extends TestCase
{
    public function testItIsNamedAfterTheConfigurationSection()
    {
        $this->assertSame('key_management', new KeyManagementDataCollector()->getName());
    }

    public function testNothingIsReportedWhenNothingWasCalled()
    {
        $collector = new KeyManagementDataCollector(['default', 'aws']);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $this->assertSame([], $collector->getCallers());
        $this->assertSame([], $collector->getKeys());
        $this->assertSame(0, $collector->getOperationCount());
        $this->assertSame(0.0, $collector->getTotalTime());
        $this->assertSame(['default', 'aws'], $collector->getClients(), 'the panel says what is wired even when a request used none of it.');
    }

    /**
     * The panel has to stay readable, and the profile has to stay small, when an entity with ten
     * encrypted columns is hydrated over fifty rows.
     */
    #[RequiresPhpExtension('openssl')]
    public function testAThousandOperationsFoldIntoAsManyRowsAsThereAreScopes()
    {
        $collector = new KeyManagementDataCollector(['default']);
        $kms = TraceableKms::wrap(new InMemoryKms(), $collector, 'default');
        $store = new TraceableDataKeyStore(new InMemoryDataKeyStore(['default' => $kms]), $collector, 'store');
        $encrypter = new TraceableEnvelopeEncrypter(new StoredEnvelopeEncrypter($store), $collector, 'stored');

        $envelopes = [];
        for ($row = 0; $row < 50; ++$row) {
            for ($field = 1; $field <= 10; ++$field) {
                $envelopes[] = $encrypter->encrypt('customer.field'.$field, 'value');
            }
        }
        foreach ($envelopes as $envelope) {
            $encrypter->decrypt($envelope);
        }
        $collector->lateCollect();

        $this->assertSame(2010, $collector->getOperationCount(), '2000 envelope and store operations, plus the ten data keys they needed.');
        $this->assertCount(11, $collector->getKeys(), 'one row per scope whatever the number of payloads, plus the master key their data keys were wrapped with.');
        $this->assertCount(2, $collector->getCallers());
        $this->assertSame(10, $collector->getKmsCallCount(), 'one data key per scope, and no round trip at all to read them back.');

        $scope = array_values(array_filter($collector->getKeys(), static fn (array $key): bool => 'customer.field1' === $key['label']))[0];

        $this->assertSame(50, $scope['operations']['encrypt']);
        $this->assertSame(50, $scope['operations']['decrypt']);
        $this->assertCount(1, $scope['data_keys'], 'every payload of a scope shares one data key.');
    }

    /**
     * The aggregates are what the collector holds between two requests of a long-running process,
     * and the ORM hydrating an entity over thousands of rows is what fills them.
     */
    #[RequiresPhpExtension('openssl')]
    public function testWhatIsHeldIsBoundedByTheCallSitesAndTheKeys()
    {
        $held = static function (int $calls): int {
            $collector = new KeyManagementDataCollector();
            $encrypter = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter(TraceableKms::wrap(new InMemoryKms(), $collector, 'default')), $collector, 'default');
            $encrypter->encrypt('app', 'hello');
            $before = memory_get_usage();

            for ($i = 0; $i < $calls; ++$i) {
                $encrypter->encrypt('app', 'hello');
            }

            return memory_get_usage() - $before;
        };

        $this->assertLessThanOrEqual($held(200) + 1024, $held(20000), 'what the collector holds must not grow with the number of calls.');
    }

    #[RequiresPhpExtension('openssl')]
    public function testAFailureCrossingSeveralLayersIsCountedOnce()
    {
        $collector = new KeyManagementDataCollector();
        $kms = TraceableKms::wrap(new InMemoryKms(), $collector, 'default');
        $encrypter = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter($kms), $collector, 'default');
        $envelope = $encrypter->encrypt('app', 'hello', 'user:42');

        try {
            $encrypter->decrypt($envelope, 'user:43');
            $this->fail('The failure must reach the caller.');
        } catch (DecryptionFailedException) {
        }

        $collector->lateCollect();

        $this->assertSame(4, $collector->getOperationCount());
        $this->assertSame(1, $collector->getErrorCount(), 'the KMS refusing the data key and the envelope call that asked for it are one failure.');
    }

    #[RequiresPhpExtension('openssl')]
    public function testAScopeTooLongToPrintIsOneRowWhetherWrittenOrRead()
    {
        $collector = new KeyManagementDataCollector();
        $store = new TraceableDataKeyStore(new InMemoryDataKeyStore(), $collector, 'store');
        $encrypter = new TraceableEnvelopeEncrypter(new StoredEnvelopeEncrypter($store), $collector, 'stored');

        $encrypter->decrypt($encrypter->encrypt(str_repeat('s', 70), 'hello'));
        $collector->lateCollect();

        $scopes = array_values(array_filter($collector->getKeys(), static fn (array $key): bool => KeyManagementDataCollector::KIND_SCOPE === $key['kind']));

        $this->assertCount(1, $scopes, 'the read must land under the scope that wrote it, whatever the label the scope was shortened to.');
        $this->assertSame(1, $scopes[0]['operations']['encrypt']);
        $this->assertSame(1, $scopes[0]['operations']['decrypt']);
    }

    #[RequiresPhpExtension('openssl')]
    public function testTheTimeOfANestedCallIsNotCountedTwice()
    {
        $collector = new KeyManagementDataCollector();
        $kms = TraceableKms::wrap(new InMemoryKms(), $collector, 'default');
        $encrypter = new TraceableEnvelopeEncrypter(new EnvelopeEncrypter($kms), $collector, 'default');

        $encrypter->encrypt('app', 'hello');
        $collector->lateCollect();

        $envelope = $collector->getServices()[KeyManagementDataCollector::LAYER_ENVELOPE]['default'];
        $generate = $collector->getServices()[KeyManagementDataCollector::LAYER_KMS]['default'];

        $this->assertSame($envelope['time'], $collector->getTotalTime(), 'the envelope call already covers the data key call it made.');
        $this->assertSame($generate['time'], $collector->getKmsTime());
        $this->assertGreaterThan(0.0, $generate['time']);
    }

    public function testACallSiteReportsEveryLayerItWentThrough()
    {
        $collector = new KeyManagementDataCollector();
        $kms = TraceableKms::wrap(new InMemoryKms(), $collector, 'default');
        $store = new TraceableDataKeyStore(new InMemoryDataKeyStore(['default' => $kms]), $collector, 'store');

        $store->current('user.email');
        $collector->lateCollect();

        $caller = $collector->getCallers()[0];

        $this->assertSame(__FILE__, $caller['file']);
        $this->assertSame(2, $caller['ops']);
        $this->assertSame(1, $caller['layers'][KeyManagementDataCollector::LAYER_STORE]);
        $this->assertSame(1, $caller['layers'][KeyManagementDataCollector::LAYER_KMS], 'the data key the store minted is charged to the code that asked for it.');
        $this->assertSame(['app', 'user.email'], $caller['keys'], 'the master key the data key was minted under, then the scope it serves.');
    }

    public function testWhatLeftTheProcessIsToldApartFromWhatDidNot()
    {
        $collector = new KeyManagementDataCollector(['local', 'remote']);
        TraceableKms::wrap(new InMemoryKms(), $collector, 'local')->encrypt('app', 'hello');
        TraceableKms::wrap(new RemoteKms(), $collector, 'remote')->encrypt('app', 'hello');
        $collector->lateCollect();

        $services = $collector->getServices()[KeyManagementDataCollector::LAYER_KMS];

        $this->assertSame(1, $services['local']['origins'][KeyManagementDataCollector::ORIGIN_CORE]);
        $this->assertSame(1, $services['remote']['origins'][KeyManagementDataCollector::ORIGIN_BRIDGE], 'a backend under a Bridge namespace is one the process talks to from the outside.');
    }

    public function testResetDropsWhatWasCollected()
    {
        $collector = new KeyManagementDataCollector(['default']);
        TraceableKms::wrap(new InMemoryKms(), $collector, 'default')->encrypt('app', 'hello');
        $collector->lateCollect();
        $collector->reset();

        $this->assertSame(0, $collector->getOperationCount());

        $collector->lateCollect();

        $this->assertSame([], $collector->getKeys(), 'a reset collector must not report what the previous request did.');
        $this->assertSame(0.0, $collector->getTotalTime());
    }
}
