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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;
use Symfony\Component\KeyManagement\CompositeKms;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Test\SwitchableKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\UnreachableKms;

class CompositeKmsTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private SwitchableKms $aws;
    private SwitchableKms $azure;
    private SwitchableKms $gcp;

    protected function setUp(): void
    {
        $this->aws = new SwitchableKms(new InMemoryKms('aws'), new \RuntimeException('aws is down.'));
        $this->azure = new SwitchableKms(new InMemoryKms('azure'), new \RuntimeException('azure is down.'));
        $this->gcp = new SwitchableKms(new InMemoryKms('gcp'), new \RuntimeException('gcp is down.'));
    }

    public function testACiphertextRoundTrips()
    {
        $kms = $this->kms();

        $ciphertext = $kms->encrypt('app', 'secret', 'aad');

        $this->assertSame('app', $ciphertext->keyId, 'the key id is the one the caller named, on the first member.');
        $this->assertSame('secret', $kms->decrypt($ciphertext, 'aad'));
    }

    public function testEveryMemberWrapsAndTheFirstOneIsAskedToRead()
    {
        $kms = $this->kms();

        $kms->decrypt($kms->encrypt('app', 'secret'));

        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->aws->calls);
        $this->assertSame(['encrypt' => 1], $this->azure->calls);
        $this->assertSame(['encrypt' => 1], $this->gcp->calls);
    }

    public function testACiphertextIsReadThroughTheNextMemberWhenTheFirstIsDown()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $this->azure->down = true;

        $this->assertSame('secret', $kms->decrypt($ciphertext));
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->azure->calls, 'the recipients are asked in their configured order.');
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->gcp->calls);
    }

    public function testACiphertextIsReadWithoutTheMemberThatWroteItFirst()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');
        $survivor = new CompositeKms(self::locator(['azure' => $this->azure, 'vault' => new InMemoryKms('vault')]), ['azure' => null, 'vault' => null]);

        $this->assertSame('secret', $survivor->decrypt($ciphertext));
    }

    public function testEachWrappingIsCompleteOnItsOwn()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');

        foreach (['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp] as $name => $member) {
            $alone = new CompositeKms(self::locator([$name => $member, 'vault' => new InMemoryKms('vault')]), [$name => null, 'vault' => null]);
            $this->assertSame('secret', $alone->decrypt($ciphertext), $name);
        }
    }

    public function testAMemberPassedOverIsLogged()
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$level, $message, $context['client'], $context['exception']->getMessage()];
            }
        };
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => 'backup'], $logger);
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $kms->decrypt($ciphertext);

        $this->assertSame([['warning', 'The KMS client "{client}" failed to read a wrapping, the next member is asked.', 'aws', 'aws is down.']], $logger->records);
    }

    public function testTheFirstFailureIsReportedWhenNoMemberAnswers()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $this->azure->down = true;
        $this->gcp->down = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('aws is down.');
        $kms->decrypt($ciphertext);
    }

    public function testACiphertextWithoutAConfiguredMemberFailsDecryption()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');
        $stranger = new CompositeKms(self::locator(['vault' => new InMemoryKms('vault'), 'local' => new InMemoryKms('local')]), ['vault' => null, 'local' => null]);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Decryption failed.');
        $stranger->decrypt($ciphertext);
    }

    public function testAnUnregisteredConfiguredMemberIsReportedWhenAskedToRead()
    {
        $writer = new CompositeKms(self::locator(['aws' => $this->aws, 'rogue' => new InMemoryKms('rogue')]), ['aws' => null, 'rogue' => null]);
        $reader = new CompositeKms(self::locator([]), ['aws' => null]);

        try {
            $reader->decrypt($writer->encrypt('app', 'secret'));
            $this->fail('The configured member is not registered.');
        } catch (LogicException $e) {
            $this->assertSame('None of the KMS clients that wrapped the ciphertext ("aws") is registered on the composite client.', $e->getMessage());
        }
    }

    public function testAFrameNameDoesNotAppearInTheDecryptionError()
    {
        $name = "evil\n\x1B[31mINJECT";
        $writer = new CompositeKms(self::locator([$name => new InMemoryKms('rogue')]), [$name => null]);
        $reader = new CompositeKms(self::locator(['good' => new InMemoryKms('good')]), ['good' => null]);

        try {
            $reader->decrypt($writer->encrypt('app', 'secret'));
            $this->fail('The unconfigured client must not be asked to decrypt.');
        } catch (DecryptionFailedException $e) {
            $this->assertSame('Decryption failed.', $e->getMessage());
        }
    }

    public function testAFrameCannotSelectARegisteredClientOutsideTheMembers()
    {
        $rogue = new SwitchableKms(new InMemoryKms('rogue'));
        $writer = new CompositeKms(self::locator(['rogue' => $rogue]), ['rogue' => null]);
        $reader = new CompositeKms(self::locator(['good' => new InMemoryKms('good'), 'rogue' => $rogue]), ['good' => null]);
        $ciphertext = $writer->encrypt('app', 'chosen plaintext');

        try {
            $reader->decrypt($ciphertext);
            $this->fail('The unconfigured client must not be asked to decrypt.');
        } catch (DecryptionFailedException $e) {
            $this->assertSame('Decryption failed.', $e->getMessage());
        }

        $this->assertSame(['encrypt' => 1], $rogue->calls);
    }

    public function testARetiredMemberReadsOldFramesWithoutWrappingNewOnes()
    {
        $old = new SwitchableKms(new InMemoryKms('old'));
        $new = new SwitchableKms(new InMemoryKms('new'));
        $original = new CompositeKms(self::locator(['old' => $old]), ['old' => null]);
        $ciphertext = $original->encrypt('app', 'before the rename');
        $dataKey = $original->generateDataKey('app');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);
        $renamed = new CompositeKms(self::locator(['old' => $old, 'new' => $new]), ['new' => null], null, ['old']);

        $this->assertSame('before the rename', $renamed->decrypt($ciphertext));
        $this->assertSame($plaintext, $renamed->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame('before the composite', $renamed->decrypt($old->encrypt('app', 'before the composite')));
        $this->assertSame('after the rename', $renamed->decrypt($renamed->encrypt('app', 'after the rename')));
        $this->assertSame(['encrypt' => 2, 'generateDataKey' => 1, 'decrypt' => 2, 'unwrapDataKey' => 1], $old->calls);
        $this->assertSame(['decrypt' => 2, 'encrypt' => 1], $new->calls);
    }

    public function testARetiredMemberCannotAlsoBeActive()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMS client "old" cannot be both an active and a retired member.');

        new CompositeKms(self::locator([]), ['old' => null], null, ['old']);
    }

    public function testARetiredMemberCannotBeListedTwice()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The retired KMS client "old" is listed more than once.');

        new CompositeKms(self::locator([]), ['new' => null], null, ['old', 'old']);
    }

    #[DataProvider('provideInvalidRetiredMembers')]
    public function testRetiredMembersMustBeAListOfValidNames(array $retired)
    {
        $this->expectException(InvalidArgumentException::class);

        new CompositeKms(self::locator([]), ['new' => null], null, $retired);
    }

    public static function provideInvalidRetiredMembers(): iterable
    {
        yield 'map' => [['old' => 'old']];
        yield 'non-string' => [[1]];
        yield 'empty' => [['']];
        yield 'too long' => [[str_repeat('x', 256)]];
    }

    public function testAnUnconfiguredClientIsNotAskedWhenAConfiguredMemberFails()
    {
        $good = new SwitchableKms(new InMemoryKms('good'));
        $rogue = new SwitchableKms(new InMemoryKms('rogue'));
        $writer = new CompositeKms(self::locator(['good' => $good, 'rogue' => $rogue]), ['good' => null, 'rogue' => null]);
        $ciphertext = $writer->encrypt('app', 'chosen plaintext');
        $reader = new CompositeKms(self::locator(['good' => $good, 'rogue' => $rogue]), ['good' => null]);
        $good->down = true;

        try {
            $reader->decrypt($ciphertext);
            $this->fail('The unconfigured client must not be used as a fallback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The backend is down.', $e->getMessage());
        }

        $this->assertSame(['encrypt' => 1], $rogue->calls);
    }

    public function testADataKeyCannotBeUnwrappedThroughAnUnconfiguredClient()
    {
        $rogue = new SwitchableKms(new InMemoryKms('rogue'));
        $writer = new CompositeKms(self::locator(['rogue' => $rogue]), ['rogue' => null]);
        $reader = new CompositeKms(self::locator(['good' => new InMemoryKms('good'), 'rogue' => $rogue]), ['good' => null]);
        $wrapped = $writer->generateDataKey('app')->wrapped;

        try {
            $reader->unwrapDataKey($wrapped);
            $this->fail('The unconfigured client must not be asked to unwrap the data key.');
        } catch (DecryptionFailedException $e) {
            $this->assertSame('Decryption failed.', $e->getMessage());
        }

        $this->assertSame(['generateDataKey' => 1], $rogue->calls);
    }

    public function testACompositeCannotReadThroughAnotherComposite()
    {
        $nested = new CompositeKms(self::locator(['good' => new InMemoryKms('good')]), ['good' => null]);
        $writer = new CompositeKms(self::locator(['nested' => new InMemoryKms('nested')]), ['nested' => null]);
        $reader = new CompositeKms(self::locator(['nested' => $nested]), ['nested' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A composite KMS client cannot be a member of another composite client.');
        $reader->decrypt($writer->encrypt('app', 'secret'));
    }

    #[DataProvider('provideSelfMemberDecorators')]
    public function testACompositeCannotReadThroughItself(string $decorator, string $message)
    {
        $clients = new class implements ContainerInterface {
            public CompositeKms $self;
            public string $decorator = 'direct';
            public int $reads = 0;

            public function get(string $id)
            {
                if (1 < ++$this->reads) {
                    throw new \RuntimeException('Recursive read.');
                }

                return match ($this->decorator) {
                    'traceable' => TraceableKms::wrap($this->self, new KeyManagementDataCollector(), 'self'),
                    'switchable' => new SwitchableKms($this->self),
                    'real' => new InMemoryKms('self'),
                    default => $this->self,
                };
            }

            public function has(string $id): bool
            {
                return 'self' === $id;
            }
        };
        $clients->self = new CompositeKms($clients, ['self' => null]);
        $clients->decorator = $decorator;
        $writer = new CompositeKms(self::locator(['self' => new InMemoryKms('self')]), ['self' => null]);
        $ciphertext = $writer->encrypt('app', 'secret');

        try {
            $clients->self->decrypt($ciphertext);
            $this->fail('The composite must not read through itself.');
        } catch (LogicException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertSame(1, $clients->reads);
        $clients->decorator = 'real';
        $clients->reads = 0;
        $this->assertSame('secret', $clients->self->decrypt($ciphertext), 'a failed recursive read must not block later reads.');
    }

    public static function provideSelfMemberDecorators(): iterable
    {
        yield 'direct' => ['direct', 'A composite KMS client cannot be a member of another composite client.'];
        yield 'traceable' => ['traceable', 'A composite KMS client cannot be a member of another composite client.'];
        yield 'custom decorator' => ['switchable', 'A composite KMS client cannot be re-entered while one of its operations is running.'];
    }

    #[DataProvider('provideReadOperations')]
    public function testARecursiveMemberCannotBeSkippedWhenAnotherMemberCanRead(string $operation)
    {
        $good = new SwitchableKms(new InMemoryKms('good'));
        $writer = new CompositeKms(self::locator(['self' => new InMemoryKms('self'), 'good' => $good]), ['self' => null, 'good' => null]);
        $ciphertext = 'decrypt' === $operation ? $writer->encrypt('app', 'secret') : $writer->generateDataKey('app')->wrapped;
        $clients = new class($good) implements ContainerInterface {
            public CompositeKms $self;

            public function __construct(private readonly SwitchableKms $good)
            {
            }

            public function get(string $id)
            {
                return 'self' === $id ? new SwitchableKms($this->self) : $this->good;
            }

            public function has(string $id): bool
            {
                return 'self' === $id || 'good' === $id;
            }
        };
        $reader = new CompositeKms($clients, ['self' => null, 'good' => null]);
        $clients->self = $reader;

        try {
            if ('decrypt' === $operation) {
                $reader->decrypt($ciphertext);
            } else {
                $reader->unwrapDataKey($ciphertext);
            }
            $this->fail('Reentry must stop the read before another member answers.');
        } catch (LogicException $e) {
            $this->assertSame('A composite KMS client cannot be re-entered while one of its operations is running.', $e->getMessage());
        }

        $this->assertSame(['encrypt' => 1], $good->calls);
    }

    public static function provideReadOperations(): iterable
    {
        yield 'decrypt' => ['decrypt'];
        yield 'unwrap data key' => ['unwrapDataKey'];
    }

    public function testIndependentFibersCanReadThroughTheSameComposite()
    {
        $clients = new class implements ContainerInterface {
            public bool $suspend = false;

            public function get(string $id)
            {
                if ($this->suspend) {
                    \Fiber::suspend();
                }

                return new InMemoryKms('member');
            }

            public function has(string $id): bool
            {
                return 'member' === $id;
            }
        };
        $kms = new CompositeKms($clients, ['member' => null]);
        $ciphertext = $kms->encrypt('app', 'secret');
        $clients->suspend = true;
        $first = new \Fiber(static fn (): string => $kms->decrypt($ciphertext));
        $second = new \Fiber(static fn (): string => $kms->decrypt($ciphertext));

        $first->start();
        $second->start();

        $first->resume();
        $second->resume();

        $this->assertSame('secret', $first->getReturn());
        $this->assertSame('secret', $second->getReturn());
    }

    #[DataProvider('provideSchedulerContexts')]
    public function testASchedulerCanResumeAnotherReaderDuringAMemberCall(bool $outerInFiber)
    {
        $member = new class(new InMemoryKms('member')) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public ?\Fiber $waiting = null;

            public function __construct(private InMemoryKms $inner)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->inner->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                if (null !== $this->waiting) {
                    $waiting = $this->waiting;
                    $this->waiting = null;
                    $waiting->resume();
                }

                return $this->inner->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->inner->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey($wrapped, $aad);
            }
        };
        $kms = new CompositeKms(self::locator(['member' => $member]), ['member' => null]);
        $ciphertext = $kms->encrypt('app', 'secret');
        $waiting = new \Fiber(static function () use ($kms, $ciphertext): string {
            \Fiber::suspend();

            return $kms->decrypt($ciphertext);
        });
        $waiting->start();
        $member->waiting = $waiting;

        if ($outerInFiber) {
            $outer = new \Fiber(static fn (): string => $kms->decrypt($ciphertext));
            $outer->start();
            $this->assertSame('secret', $outer->getReturn());
        } else {
            $this->assertSame('secret', $kms->decrypt($ciphertext));
        }

        $this->assertSame('secret', $waiting->getReturn());
    }

    public static function provideSchedulerContexts(): iterable
    {
        yield 'main context' => [false];
        yield 'fiber context' => [true];
    }

    #[DataProvider('provideWriteOperations')]
    public function testACompositeCannotWriteThroughItselfBehindACustomDecorator(string $operation)
    {
        $clients = new class implements ContainerInterface {
            public CompositeKms $self;
            public int $resolutions = 0;
            public bool $recursive = true;

            public function get(string $id)
            {
                if (!$this->recursive) {
                    return new InMemoryKms('self');
                }

                if (1 < ++$this->resolutions) {
                    throw new \RuntimeException('Recursive write.');
                }

                return new SwitchableKms($this->self);
            }

            public function has(string $id): bool
            {
                return 'self' === $id;
            }
        };
        $clients->self = new CompositeKms($clients, ['self' => null]);

        try {
            if ('encrypt' === $operation) {
                $clients->self->encrypt('app', 'secret');
            } else {
                $clients->self->generateDataKey('app');
            }
            $this->fail('The composite must not write through itself.');
        } catch (LogicException $e) {
            $this->assertSame('A composite KMS client cannot be re-entered while one of its operations is running.', $e->getMessage());
        }

        $this->assertSame(1, $clients->resolutions);
        $clients->recursive = false;
        $this->assertSame('secret', $clients->self->decrypt($clients->self->encrypt('app', 'secret')));
    }

    public static function provideWriteOperations(): iterable
    {
        yield 'encrypt' => ['encrypt'];
        yield 'generate data key' => ['generateDataKey'];
    }

    public function testAMemberThatCannotWrapFailsTheWrite()
    {
        $kms = $this->kms();
        $this->gcp->down = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gcp is down.');
        $kms->encrypt('app', 'secret');
    }

    public function testTheAadBindsEveryWrapping()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret', 'aad');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext, 'other');
    }

    public function testTheDeterministicFlagReachesEveryMember()
    {
        $this->kms()->encrypt('app', 'secret', deterministic: true);

        $this->assertSame([true], $this->aws->deterministic);
        $this->assertSame([true], $this->azure->deterministic);
        $this->assertSame([true], $this->gcp->deterministic);
    }

    public function testADataKeyIsWrappedByEveryMemberAndUnwrappedByAny()
    {
        $kms = $this->kms();

        $dataKey = $kms->generateDataKey('app', 32, 'aad');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame(32, \strlen($plaintext));
        $this->assertSame('app', $dataKey->wrapped->keyId);
        $this->assertSame(['generateDataKey' => 1], $this->aws->calls, 'the first member mints the key.');
        $this->assertSame(['encrypt' => 1], $this->azure->calls, 'the others wrap its plaintext.');

        foreach (['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp] as $name => $member) {
            $alone = new CompositeKms(self::locator([$name => $member, 'vault' => new InMemoryKms('vault')]), [$name => null, 'vault' => null]);
            $this->assertSame($plaintext, $alone->unwrapDataKey($dataKey->wrapped, 'aad')->use(static fn (string $key): string => $key), $name);
        }
    }

    public function testADataKeyIsUnwrappedThroughTheNextMemberWhenTheFirstIsDown()
    {
        $kms = $this->kms();
        $dataKey = $kms->generateDataKey('app');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->aws->down = true;

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
    }

    public function testTheMintedDataKeyIsConsumed()
    {
        $minting = self::minting(random_bytes(32));
        $kms = new CompositeKms(self::locator(['aws' => $minting, 'azure' => $this->azure]), ['aws' => null, 'azure' => 'backup']);

        $dataKey = $kms->generateDataKey('app');

        $this->assertTrue($minting->minted->isConsumed());
        $this->assertFalse($dataKey->isConsumed());
        $this->assertSame(32, \strlen($dataKey->use(static fn (string $key): string => $key)));
    }

    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $known = random_bytes(32);
        $kms = new CompositeKms(self::locator(['aws' => self::minting($known), 'azure' => new UnreachableKms()]), ['aws' => null, 'azure' => 'backup']);

        $trace = self::traceOf(static fn () => $kms->generateDataKey('app'));

        self::assertRedacted($known, $trace);
    }

    public function testAMemberThatCannotReadBackIsRefused()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => new EncryptOnlyKms()]), ['aws' => null, 'azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The KMS client "azure" cannot be a member of a composite client');
        $kms->encrypt('app', 'secret');
    }

    public function testAnUnregisteredMemberIsReportedWhenAskedToWrap()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws]), ['aws' => null, 'azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No KMS client named "azure" is registered on the composite client.');
        $kms->encrypt('app', 'secret');
    }

    public function testAtLeastOneMemberIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A composite KMS client needs at least one member.');

        new CompositeKms(self::locator(['aws' => $this->aws]), []);
    }

    public function testAProviderLostForGoodLeavesTheSurvivorWorkingAlone()
    {
        $pair = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => null]);
        $written = $pair->encrypt('app', 'written while both were alive');

        $degraded = new CompositeKms(self::locator(['azure' => $this->azure]), ['azure' => null]);
        $meanwhile = $degraded->encrypt('app', 'written while degraded');

        $this->assertSame('written while both were alive', $degraded->decrypt($written));
        $this->assertSame('written while degraded', $degraded->decrypt($meanwhile));

        $restored = new CompositeKms(self::locator(['azure' => $this->azure, 'gcp' => $this->gcp]), ['azure' => null, 'gcp' => null]);

        $this->assertSame('written while both were alive', $restored->decrypt($written));
        $this->assertSame('written while degraded', $restored->decrypt($meanwhile));
    }

    public function testAPlainMemberDoesNotReadAFrame()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');

        $this->expectException(DecryptionFailedException::class);
        $this->aws->decrypt($ciphertext);
    }

    public function testAMemberWithoutAMasterKeyUsesTheOneEachCallNames()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => null]);

        $ciphertext = $kms->encrypt('app', 'secret');
        $survivor = new CompositeKms(self::locator(['azure' => $this->azure, 'gcp' => $this->gcp]), ['azure' => null, 'gcp' => null]);

        $this->assertSame('secret', $survivor->decrypt($ciphertext));
        $this->assertSame('app', $this->azure->keyIds[0]);
    }

    public function testAMemberNameHasToFitInTheCiphertext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is 256 bytes long');

        new CompositeKms(self::locator(['aws' => $this->aws]), ['aws' => null, str_repeat('a', 256) => 'backup']);
    }

    public function testAMasterKeyIdHasToFitInTheCiphertext()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => str_repeat('k', 0x10000)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The master key id of the KMS client "azure" is too long');
        $kms->encrypt('app', 'secret');
    }

    #[DataProvider('provideMalformedBlobs')]
    public function testABlobThatDoesNotParseIsAnUnreadableCiphertext(string $blob)
    {
        $this->expectException(DecryptionFailedException::class);

        $this->kms()->decrypt(new Ciphertext($blob, 'app'));
    }

    public static function provideMalformedBlobs(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown version' => ["\x02\x01"];
        yield 'no wrapping' => ["\x01\x00"];
        yield 'truncated name' => ["\x01\x01\x03aw"];
        yield 'empty name' => ["\x01\x01\x00\x00\x03app\x00\x00\x00\x01x"];
        yield 'truncated blob' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x10x"];
        yield 'trailing bytes' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x01x!"];
    }

    #[RequiresPhpExtension('openssl')]
    public function testAnEnvelopeWrittenThroughTheCompositeClientIsReadWithoutItsFirstMember()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $encrypter = new EnvelopeEncrypter(new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup']));
        $vault = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $survivor = new EnvelopeEncrypter(new CompositeKms(self::locator(['azure' => $azure, 'vault' => $vault]), ['azure' => null, 'vault' => null]));

        $envelope = $encrypter->encrypt('app', 'a payload of any size', 'aad');

        $this->assertSame('a payload of any size', $survivor->decrypt(Envelope::fromBytes((string) $envelope), 'aad'));
    }

    #[RequiresPhpExtension('openssl')]
    public function testNoEnvelopeIsWrittenWhileAMemberIsDown()
    {
        $encrypter = new EnvelopeEncrypter($this->kms());
        $this->azure->down = true;

        try {
            $encrypter->encrypt('app', 'a payload');
            $this->fail('No envelope must be written.');
        } catch (\RuntimeException $e) {
            $this->assertSame('azure is down.', $e->getMessage());
        }

        $this->assertSame(['generateDataKey' => 1], $this->aws->calls);
        $this->assertSame([], $this->gcp->calls, 'the members after the one that failed are not asked either.');
    }

    #[RequiresPhpExtension('openssl')]
    public function testAStoreIsRewrappedUnderNewMembersThroughTheCommand()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $gcp = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));

        $redundant = new class(new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup'])) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(public CompositeKms $members)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->members->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->members->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->members->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->members->unwrapDataKey($wrapped, $aad);
            }
        };
        $store = new InMemoryDataKeyStore(['redundant' => $redundant], 'redundant', 'app');
        $encrypter = new StoredEnvelopeEncrypter($store);
        $envelope = $encrypter->encrypt('user.email', 'survives the loss of aws');
        $store->forget();

        $redundant->members = new CompositeKms(self::locator(['azure' => $azure, 'gcp' => $gcp]), ['azure' => null, 'gcp' => 'backup']);
        $tester = new CommandTester(new RewrapDataKeysCommand($store, self::locator(['redundant' => $redundant])));
        $tester->execute(['--from' => 'redundant', '--to' => 'redundant', '--key-id' => 'backup']);
        $tester->assertCommandIsSuccessful();
        $store->forget();

        $redundant->members = new CompositeKms(self::locator(['gcp' => $gcp, 'vault' => new InMemoryKms('vault')]), ['gcp' => null, 'vault' => null]);
        $this->assertSame('survives the loss of aws', $encrypter->decrypt($envelope));
    }

    public function testAStoredKeyCanBeMigratedAwayFromARetiredMember()
    {
        $old = new InMemoryKms('old');
        $new = new InMemoryKms('new');
        $main = new class(new CompositeKms(self::locator(['old' => $old]), ['old' => null])) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(public CompositeKms $members)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->members->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->members->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->members->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->members->unwrapDataKey($wrapped, $aad);
            }
        };
        $store = new InMemoryDataKeyStore(['main' => $main], 'main', 'app');
        $encrypter = new StoredEnvelopeEncrypter($store);
        $envelope = $encrypter->encrypt('user.email', 'before the rename');
        $store->forget();

        $main->members = new CompositeKms(self::locator(['old' => $old, 'new' => $new]), ['new' => null], null, ['old']);
        $tester = new CommandTester(new RewrapDataKeysCommand($store, self::locator(['main' => $main])));
        $tester->execute(['--from' => 'main', '--to' => 'main', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();
        $store->forget();

        $main->members = new CompositeKms(self::locator(['new' => $new]), ['new' => null]);
        $this->assertSame('before the rename', $encrypter->decrypt($envelope));
    }

    public function testACiphertextAMemberWroteOnItsOwnIsRead()
    {
        $ciphertext = $this->azure->encrypt('backup', 'written before the switch', 'aad');
        $dataKey = $this->azure->generateDataKey('backup', 32, 'aad');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);
        $kms = $this->kms();

        $this->assertSame('written before the switch', $kms->decrypt($ciphertext, 'aad'));
        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped, 'aad')->use(static fn (string $key): string => $key));
        $this->assertSame(['encrypt' => 1, 'generateDataKey' => 1, 'decrypt' => 1, 'unwrapDataKey' => 1], $this->azure->calls);
        $this->assertSame(['decrypt' => 1, 'unwrapDataKey' => 1], $this->aws->calls, 'the members before the one that wrote it are asked first, and fail.');
        $this->assertSame([], $this->gcp->calls);
    }

    public function testAWrappingIsReadBackByTheOperationThatWroteIt()
    {
        $aws = new SwitchableKms(self::twoOperations(new InMemoryKms('aws')));
        $azure = new SwitchableKms(self::twoOperations(new InMemoryKms('azure')));
        $kms = new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup']);

        $dataKey = $kms->generateDataKey('app');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame(['generateDataKey' => 1, 'unwrapDataKey' => 1], $aws->calls, 'the minter reads its wrapping back the way it wrote it.');

        $aws->down = true;

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $azure->calls, 'the other members read their wrapping back with decrypt(), which is what wrote it.');
    }

    public function testAFirstMemberWithSeparateOperationsCannotUnwrapWhatEncryptWrote()
    {
        $first = self::twoOperations(new InMemoryKms('first'));
        $kms = new CompositeKms(self::locator(['first' => $first]), ['first' => null]);

        $this->expectException(DecryptionFailedException::class);
        $kms->unwrapDataKey($kms->encrypt('app', 'key material'));
    }

    public function testTheDataKeyReadThroughAnyMemberRefersToTheCompositeCiphertext()
    {
        $kms = $this->kms();
        $dataKey = $kms->generateDataKey('app');

        $this->assertSame($dataKey->wrapped, $kms->unwrapDataKey($dataKey->wrapped)->wrapped);

        $this->aws->down = true;

        $this->assertSame($dataKey->wrapped, $kms->unwrapDataKey($dataKey->wrapped)->wrapped);
    }

    private static function twoOperations(InMemoryKms $inner): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        return new class($inner) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(private readonly InMemoryKms $inner)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return new Ciphertext('encrypt:'.$this->inner->encrypt($keyId, $plaintext, $aad, $deterministic)->blob, $keyId);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->inner->decrypt($this->strip('encrypt:', $ciphertext), $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->inner->generateDataKey($keyId, $length, $aad)->use(fn (#[\SensitiveParameter] string $plaintext): DataKey => new DataKey($plaintext, new Ciphertext('wrap:'.$this->inner->encrypt($keyId, $plaintext, $aad)->blob, $keyId)));
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey($this->strip('wrap:', $wrapped), $aad);
            }

            private function strip(string $operation, Ciphertext $ciphertext): Ciphertext
            {
                if (!str_starts_with($ciphertext->blob, $operation)) {
                    throw new DecryptionFailedException();
                }

                return new Ciphertext(substr($ciphertext->blob, \strlen($operation)), $ciphertext->keyId);
            }
        };
    }

    private function kms(): CompositeKms
    {
        return new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp]), ['aws' => null, 'azure' => 'backup', 'gcp' => 'projects/p/keys/backup']);
    }

    private static function minting(#[\SensitiveParameter] string $known): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        return new class($known) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public ?DataKey $minted = null;
            private readonly InMemoryKms $inner;

            public function __construct(#[\SensitiveParameter] private readonly string $known)
            {
                $this->inner = new InMemoryKms();
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->inner->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->inner->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->minted = new DataKey($this->known, $this->inner->encrypt($keyId, $this->known, $aad));
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey($wrapped, $aad);
            }
        };
    }

    /**
     * @param array<string, object> $clients
     */
    private static function locator(array $clients): ServiceLocator
    {
        $factories = [];
        foreach ($clients as $name => $client) {
            $factories[$name] = static fn (): object => $client;
        }

        return new ServiceLocator($factories);
    }

    public function testAMemberIsPassedOverWhateverItThrows()
    {
        foreach ([new \DomainException('down.'), new \Exception('down.'), new \InvalidArgumentException('down.'), new LogicException('down.')] as $failure) {
            $first = new SwitchableKms(new InMemoryKms(), $failure);
            $kms = new CompositeKms(self::locator(['aws' => $first, 'azure' => new InMemoryKms()]), ['aws' => null, 'azure' => null]);
            $ciphertext = $kms->encrypt('app', 'secret');
            $first->down = true;

            $this->assertSame('secret', $kms->decrypt($ciphertext), $failure::class);
        }
    }

    public function testTheNumberOfMembersHasToFitInTheCiphertext()
    {
        $members = [];
        for ($i = 0; $i <= 0xFF; ++$i) {
            $members['m'.$i] = null;
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A composite KMS client has at most 255 members, 256 given.');

        new CompositeKms(self::locator([]), $members);
    }
}
