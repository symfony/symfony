<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Test;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\RedundantKms;

/**
 * A KMS client that can be taken down in the middle of a test, and that counts what it is asked.
 *
 * While up, every call goes to the client it wraps. While down, every call throws `$failure`, by
 * default the plain RuntimeException an SDK throws when its backend is unreachable, which the
 * component does not classify: what the code under test does with it is what a test is after,
 * typically that a {@see RedundantKms} keeps reading through its other members and refuses to
 * write. The calls are counted either way, so a test can also tell which client was asked, and
 * with what.
 *
 *     $aws = new SwitchableKms(new InMemoryKms());
 *     $kms = new RedundantKms(new ServiceLocator(['aws' => fn () => $aws, 'azure' => fn () => new InMemoryKms()]), ['aws' => null, 'azure' => 'backup']);
 *     $ciphertext = $kms->encrypt('app', 'secret');
 *
 *     $aws->down = true;
 *     $kms->decrypt($ciphertext); // still "secret"
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SwitchableKms implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface
{
    public bool $down = false;

    /**
     * @var array<string, int> Number of calls, keyed by method name
     */
    public array $calls = [];

    /**
     * @var list<string> The key id of each encrypt() and generateDataKey() call, in order
     */
    public array $keyIds = [];

    /**
     * @var list<bool> The `$deterministic` flag of each encrypt() call
     */
    public array $deterministic = [];

    private readonly \Throwable $failure;

    public function __construct(
        private readonly DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface $inner,
        ?\Throwable $failure = null,
    ) {
        $this->failure = $failure ?? new \RuntimeException('The backend is down.');
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        $this->keyIds[] = $keyId;
        $this->deterministic[] = $deterministic;

        return $this->up(__FUNCTION__)->encrypt($keyId, $plaintext, $aad, $deterministic);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return $this->up(__FUNCTION__)->decrypt($ciphertext, $aad);
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        $this->keyIds[] = $keyId;

        return $this->up(__FUNCTION__)->generateDataKey($keyId, $length, $aad);
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return $this->up(__FUNCTION__)->unwrapDataKey($wrapped, $aad);
    }

    private function up(string $method): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;

        if ($this->down) {
            throw $this->failure;
        }

        return $this->inner;
    }
}
