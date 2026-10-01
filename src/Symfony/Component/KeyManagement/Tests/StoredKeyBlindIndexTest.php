<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Email;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Verbatim;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredKeyBlindIndex;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Tests\Fixtures\CountingKms;

#[RequiresPhpExtension('openssl')]
class StoredKeyBlindIndexTest extends TestCase
{
    private OpenSslKms $kms;

    protected function setUp(): void
    {
        $this->kms = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
    }

    public function testTwoIndexesOverOneStoredKeyShareItsOpening()
    {
        $counting = new CountingKms($this->kms);
        $store = new InMemoryDataKeyStore(['default' => $counting]);
        $reference = $store->current('blind-index')->reference;
        $store->forget();

        (new StoredKeyBlindIndex($store, $reference, new Verbatim()))->of('ada@example.org');
        (new StoredKeyBlindIndex($store, $reference, new Email()))->of('ada@example.org');

        $this->assertSame(1, $counting->unwrapped);
    }

    public function testAStoredKeyGivesTheSameTagAsItsWrappedForm()
    {
        $store = new InMemoryDataKeyStore(['default' => $this->kms]);
        $reference = $store->current('blind-index')->reference;
        $stored = iterator_to_array($store->all())[0];

        $this->assertSame(
            (new BlindIndex($this->kms, $stored->wrapped, new Email()))->of('  Ada@Example.ORG '),
            (new StoredKeyBlindIndex($store, $reference, new Email()))->of('Ada@example.org'),
        );
    }

    public function testTheStoreIsAskedOnceAndNotOncePerValue()
    {
        $store = new InMemoryDataKeyStore();
        $reference = $store->current('blind-index')->reference;
        $counting = new class($store) implements DataKeyStoreInterface {
            public int $gets = 0;

            public function __construct(private readonly DataKeyStoreInterface $inner)
            {
            }

            public function current(string $scope): DataKeyHandle
            {
                return $this->inner->current($scope);
            }

            public function get(string $reference): DataKeyHandle
            {
                ++$this->gets;

                return $this->inner->get($reference);
            }
        };

        $index = new StoredKeyBlindIndex($counting, $reference, new Verbatim());
        for ($i = 0; $i < 50; ++$i) {
            $index->of('value-'.$i);
        }

        $this->assertSame(1, $counting->gets, 'a store reads a row to answer, which is not a price to pay per value');
    }

    public function testAnIndexOutlivesTheKeyTheStoreReleased()
    {
        $counting = new CountingKms($this->kms);
        $store = new InMemoryDataKeyStore(['default' => $counting]);
        $reference = $store->current('blind-index')->reference;

        $index = new StoredKeyBlindIndex($store, $reference, new Verbatim());
        $tag = $index->of('ada@example.org');
        $store->forget();

        $this->assertSame($tag, $index->of('ada@example.org'));
        $this->assertSame(1, $counting->unwrapped, 'the key was opened again rather than held across the reset');
    }

    public function testAnIndexKeyTheStoreDoesNotHoldIsReported()
    {
        $index = new StoredKeyBlindIndex(new InMemoryDataKeyStore(), 'no-such-reference', new Verbatim());

        $this->expectException(DataKeyNotFoundException::class);
        $index->of('ada@example.org');
    }
}
