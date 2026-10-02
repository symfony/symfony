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
use Symfony\Component\KeyManagement\BlindIndex\Projection\Email;
use Symfony\Component\KeyManagement\BlindIndex\Projection\EmailDomain;
use Symfony\Component\KeyManagement\BlindIndex\HmacSha256;
use Symfony\Component\KeyManagement\BlindIndex\ProjectionInterface;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Verbatim;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\CountingKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;

#[RequiresPhpExtension('openssl')]
class BlindIndexTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private OpenSslKms $kms;
    private Ciphertext $wrappedKey;

    protected function setUp(): void
    {
        $this->kms = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->wrappedKey = $this->kms->generateDataKey('app')->wrapped;
    }

    public function testEqualValuesGiveEqualTags()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim());

        $this->assertSame($index->of('ada@example.org'), $index->of('ada@example.org'));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('bob@example.org'));
    }

    public function testTheTagIsAlwaysTheSameWidth()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index->of(''));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index->of(str_repeat('x', 10_000)));
    }

    public function testTheTagCannotBeReproducedWithoutTheKey()
    {
        $tag = (new BlindIndex($this->kms, $this->wrappedKey, new Verbatim()))->of('ada@example.org');

        $this->assertNotSame(hash('sha256', 'ada@example.org'), $tag);
        $this->assertNotSame(bin2hex(hash_hmac('sha256', 'ada@example.org', 'app', true)), $tag);

        $another = new BlindIndex($this->kms, $this->kms->generateDataKey('app')->wrapped, new Verbatim());
        $this->assertNotSame($tag, $another->of('ada@example.org'), 'another index key gives another tag');
    }

    public function testTheVerbatimProjectionFoldsNothing()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim());

        $this->assertNotSame($index->of('Ada'), $index->of('ada'));
        $this->assertNotSame($index->of(' ada'), $index->of('ada'));
    }

    public function testAnEmailIsFoldedTheWayTheStandardSaysAndNoFurther()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Email());

        $this->assertSame($index->of('ada@example.org'), $index->of('  ada@Example.ORG '));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('Ada@example.org'));
        $this->assertNotSame($index->of('ada@example.org'), $index->of('bob@example.org'));
    }

    public function testSomethingThatIsNotAnAddressIsIndexedTrimmedAndWhole()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Email());

        $this->assertSame($index->of('not-an-address'), $index->of('  not-an-address '));
        $this->assertNotSame($index->of('not-an-address'), $index->of('NOT-AN-ADDRESS'));
    }

    public function testTheDomainProjectionGroupsAddressesOfOneCompany()
    {
        $domain = new BlindIndex($this->kms, $this->wrappedKey, new EmailDomain());
        $address = new BlindIndex($this->kms, $this->wrappedKey, new Email());

        $this->assertSame($domain->of('ada@example.org'), $domain->of('BOB@Example.org'));
        $this->assertNotSame($domain->of('ada@example.org'), $domain->of('ada@other.org'));
        $this->assertNotSame($domain->of('ada@example.org'), $address->of('ada@example.org'));
    }

    public function testTheDomainIsIndexedFromAnAddressOrFromItself()
    {
        $domain = new BlindIndex($this->kms, $this->wrappedKey, new EmailDomain());

        $this->assertSame($domain->of('ada@example.org'), $domain->of('example.org'));
        $this->assertSame($domain->of('ada@example.org'), $domain->of('  Example.ORG '));
        $this->assertNotSame($domain->of('example.org'), $domain->of('other.org'));
        $this->assertSame($domain->of('a@b@example.org'), $domain->of('example.org'), 'the last @ is the separator');
    }

    public function testAnApplicationIndexesItsOwnProjection()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new class implements ProjectionInterface {
            public function project(string $value): string
            {
                return substr(preg_replace('/\D+/', '', $value), -4);
            }
        });

        $this->assertSame($index->of('+33 6 12 34 56 78'), $index->of('5678'));
        $this->assertNotSame($index->of('+33 6 12 34 56 78'), $index->of('1234'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheAlgorithmChangesTheTagAndIsThereforePartOfTheFormat()
    {
        $hmac = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim(), new HmacSha256());
        $blake = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim(), new Blake2b());

        $this->assertNotSame($hmac->of('ada@example.org'), $blake->of('ada@example.org'));
        $this->assertSame($blake->of('ada@example.org'), $blake->of('ada@example.org'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $blake->of('ada@example.org'));
    }

    public function testTheDefaultAlgorithmIsTheKeyedHmac()
    {
        $given = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim(), new HmacSha256());
        $omitted = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim());

        $this->assertSame($given->of('ada@example.org'), $omitted->of('ada@example.org'));
    }

    public function testTheShippedAlgorithmsAgreeOnTheTagWidth()
    {
        $this->assertSame(AlgorithmInterface::TAG_BYTES, \strlen(new HmacSha256()->tag('ada@example.org', str_repeat('k', 32))));

        if (\function_exists('sodium_crypto_generichash')) {
            $this->assertSame(AlgorithmInterface::TAG_BYTES, \strlen(new Blake2b()->tag('ada@example.org', str_repeat('k', 32))));
        }
    }

    public function testAnAlgorithmReturningAnotherWidthIsRefused()
    {
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim(), new class implements AlgorithmInterface {
            public function tag(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
            {
                return hash_hmac('sha512', $value, $key, true);
            }
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('returned a 64-byte tag instead of %d.', AlgorithmInterface::TAG_BYTES));

        $index->of('ada@example.org');
    }

    public function testTheKeyIsUnwrappedOnceWhateverTheNumberOfValues()
    {
        $counting = new CountingKms($this->kms);

        $index = new BlindIndex($counting, $this->wrappedKey, new Verbatim());
        for ($i = 0; $i < 50; ++$i) {
            $index->of('value-'.$i);
        }

        $this->assertSame(1, $counting->unwrapped);
    }

    public function testTwoIndexesOverOneWrappedKeyEachOpenItOnTheirOwn()
    {
        $counting = new CountingKms($this->kms);

        (new BlindIndex($counting, $this->wrappedKey, new Verbatim()))->of('ada@example.org');
        (new BlindIndex($counting, $this->wrappedKey, new Email()))->of('ada@example.org');

        $this->assertSame(2, $counting->unwrapped, 'a wrapped key leaves every index to unwrap it for itself');
    }

    public function testTheIndexKeyDoesNotReachStackTraces()
    {
        $indexKey = $this->kms->unwrapDataKey($this->wrappedKey)->use(static fn (string $key): string => $key);
        $index = new BlindIndex($this->kms, $this->wrappedKey, new Verbatim(), new class implements AlgorithmInterface {
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
        $index = new BlindIndex($this->kms, new Ciphertext('not a wrapped key', 'app'), new Verbatim());

        $this->expectException(DecryptionFailedException::class);
        $index->of('ada@example.org');
    }
}
