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
use Symfony\Component\KeyManagement\BlindIndex\AlgorithmInterface;
use Symfony\Component\KeyManagement\BlindIndex\Blake2b;
use Symfony\Component\KeyManagement\BlindIndex\Email;
use Symfony\Component\KeyManagement\BlindIndex\EmailDomain;
use Symfony\Component\KeyManagement\BlindIndex\HmacSha256;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;

#[RequiresPhpExtension('openssl')]
class BlindIndexTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private DataKeyGeneratorInterface $kms;
    private Ciphertext $wrappedKey;

    protected function setUp(): void
    {
        $this->kms = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->wrappedKey = $this->kms->generateDataKey('app')->wrapped;
    }

    public function testEqualValuesGiveEqualTags()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey);

        $this->assertSame($index->of('ada@example.org'), $index->of('ada@example.org'));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('bob@example.org'));
    }

    public function testTheTagIsAlwaysTheSameWidth()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index->of(''));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index->of(str_repeat('x', 10_000)));
    }

    /**
     * The whole point of the key: the tag of a guessed value cannot be computed without it, so an
     * attacker holding the table cannot ask whether a given address is in it.
     */
    public function testTheTagCannotBeReproducedWithoutTheKey()
    {
        $tag = (new BlindIndex($this->kms, $this->wrappedKey))->of('ada@example.org');

        $this->assertNotSame(hash('sha256', 'ada@example.org'), $tag);
        $this->assertNotSame(bin2hex(hash_hmac('sha256', 'ada@example.org', 'app', true)), $tag);

        $another = new BlindIndex($this->kms, $this->kms->generateDataKey('app')->wrapped);
        $this->assertNotSame($tag, $another->of('ada@example.org'), 'another index key gives another tag');
    }

    public function testTheBaseClassIndexesTheValueVerbatim()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey);

        $this->assertNotSame($index->of('Ada'), $index->of('ada'));
        $this->assertNotSame($index->of(' ada'), $index->of('ada'));
    }

    /**
     * RFC 5321 makes the domain case-insensitive and leaves the local part to the receiving
     * server, so only the domain is folded.
     */
    public function testAnEmailIsFoldedTheWayTheStandardSaysAndNoFurther()
    {
        $index = new Email($this->kms, $this->wrappedKey);

        $this->assertSame($index->of('ada@example.org'), $index->of('  ada@Example.ORG '));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('Ada@example.org'));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('bob@example.org'));
    }

    public function testSomethingThatIsNotAnAddressIsIndexedTrimmedAndWhole()
    {
        $index = new Email($this->kms, $this->wrappedKey);

        $this->assertSame($index->of('not-an-address'), $index->of('  not-an-address '));
        $this->assertNotSame($index->of('not-an-address'), $index->of('NOT-AN-ADDRESS'));
    }

    /**
     * The second column is how a partial search is answered here: name the question, index the
     * answer.
     */
    public function testTheDomainIndexGroupsAddressesOfOneCompany()
    {
        $domain = new EmailDomain($this->kms, $this->wrappedKey);
        $address = new Email($this->kms, $this->wrappedKey);

        $this->assertSame($domain->of('ada@example.org'), $domain->of('BOB@Example.org'));
        $this->assertNotSame($domain->of('ada@example.org'), $domain->of('ada@other.org'));
        $this->assertNotSame($domain->of('ada@example.org'), $address->of('ada@example.org'));
    }

    /**
     * Both paths need it: a row is indexed from the address it carries, a query has only the
     * domain, and the two have to meet on the same tag.
     */
    public function testTheDomainIsIndexedFromAnAddressOrFromItself()
    {
        $domain = new EmailDomain($this->kms, $this->wrappedKey);

        $this->assertSame($domain->of('ada@example.org'), $domain->of('example.org'));
        $this->assertSame($domain->of('ada@example.org'), $domain->of('  Example.ORG '));
        $this->assertNotSame($domain->of('example.org'), $domain->of('other.org'));
        $this->assertSame($domain->of('a@b@example.org'), $domain->of('example.org'), 'the last @ is the separator');
    }

    /**
     * A subclass is the whole extension point, and it cannot get at the key.
     */
    public function testAnApplicationIndexesItsOwnProjection()
    {
        $index = new class($this->kms, $this->wrappedKey) extends BlindIndex {
            protected function project(string $value): string
            {
                return substr(preg_replace('/\D+/', '', $value), -4);
            }
        };

        $this->assertSame($index->of('+33 6 12 34 56 78'), $index->of('5678'));
        $this->assertNotSame($index->of('+33 6 12 34 56 78'), $index->of('1234'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheAlgorithmChangesTheTagAndIsThereforePartOfTheFormat()
    {
        $hmac = new BlindIndex($this->kms, $this->wrappedKey, new HmacSha256());
        $blake = new BlindIndex($this->kms, $this->wrappedKey, new Blake2b());

        $this->assertNotSame($hmac->of('ada@example.org'), $blake->of('ada@example.org'));
        $this->assertSame($blake->of('ada@example.org'), $blake->of('ada@example.org'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $blake->of('ada@example.org'));
    }

    /**
     * The KMS is reached to unwrap the index key and never again, which is what makes indexing many
     * values, or many tags per value, affordable on a backend that answers over the network.
     */
    public function testTheKeyIsUnwrappedOnceWhateverTheNumberOfValues()
    {
        $counting = new class($this->kms) implements DataKeyGeneratorInterface {
            public int $unwrapped = 0;

            public function __construct(private readonly DataKeyGeneratorInterface $inner)
            {
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->inner->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                ++$this->unwrapped;

                return $this->inner->unwrapDataKey($wrapped, $aad);
            }
        };

        $index = new BlindIndex($counting, $this->wrappedKey);
        for ($i = 0; $i < 50; ++$i) {
            $index->of('value-'.$i);
        }

        $this->assertSame(1, $counting->unwrapped);
    }

    /**
     * The index key is handed to a closure, which is a function like any other: its argument lands
     * in the trace of anything the algorithm raises.
     */
    public function testTheIndexKeyDoesNotReachStackTraces()
    {
        $indexKey = $this->kms->unwrapDataKey($this->wrappedKey)->use(static fn (string $key): string => $key);
        $index = new BlindIndex($this->kms, $this->wrappedKey, new class implements AlgorithmInterface {
            public function tag(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
            {
                throw new \RuntimeException('The algorithm is unavailable.');
            }
        });

        $trace = self::traceOf(static fn () => $index->of('ada@example.org'));

        self::assertRedacted($indexKey, $trace);
    }

    public function testAnIndexKeyThatCannotBeUnwrappedIsReported()
    {
        $index = new BlindIndex($this->kms, new Ciphertext('not a wrapped key', 'app'));

        $this->expectException(DecryptionFailedException::class);
        $index->of('ada@example.org');
    }
}
