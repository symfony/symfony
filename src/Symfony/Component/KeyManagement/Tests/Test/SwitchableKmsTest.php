<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Test\SwitchableKms;

class SwitchableKmsTest extends TestCase
{
    public function testEveryCallGoesThroughWhileUp()
    {
        $kms = new SwitchableKms(new InMemoryKms());

        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret', 'aad', true), 'aad'));
        $dataKey = $kms->generateDataKey('app', 16);
        $this->assertSame(16, \strlen($kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key)));

        $this->assertSame(['encrypt' => 1, 'decrypt' => 1, 'generateDataKey' => 1, 'unwrapDataKey' => 1], $kms->calls);
        $this->assertSame(['app', 'app'], $kms->keyIds);
        $this->assertSame([true], $kms->deterministic);
    }

    public function testEveryCallThrowsWhileDown()
    {
        $failure = new \DomainException('gone fishing');
        $kms = new SwitchableKms(new InMemoryKms(), $failure);
        $ciphertext = $kms->encrypt('app', 'secret');
        $kms->down = true;

        foreach ([
            static fn () => $kms->encrypt('app', 'secret'),
            static fn () => $kms->decrypt($ciphertext),
            static fn () => $kms->generateDataKey('app'),
            static fn () => $kms->unwrapDataKey($ciphertext),
        ] as $call) {
            try {
                $call();
                $this->fail('A client that is down must throw.');
            } catch (\DomainException $e) {
                $this->assertSame($failure, $e, 'the very exception given is what a test can then expect.');
            }
        }

        $this->assertSame(['encrypt' => 2, 'decrypt' => 1, 'generateDataKey' => 1, 'unwrapDataKey' => 1], $kms->calls, 'a call is counted whether it went through or not.');
    }

    public function testTheDefaultFailureIsAPlainRuntimeException()
    {
        $kms = new SwitchableKms(new InMemoryKms());
        $kms->down = true;

        try {
            $kms->encrypt('app', 'secret');
            $this->fail('A client that is down must throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame(\RuntimeException::class, $e::class, 'what an SDK throws when its backend is unreachable, and the component does not classify.');
            $this->assertSame('The backend is down.', $e->getMessage());
        }
    }

    public function testAClientComesBackUp()
    {
        $kms = new SwitchableKms(new InMemoryKms());
        $ciphertext = $kms->encrypt('app', 'secret');

        $kms->down = true;
        $kms->down = false;

        $this->assertSame('secret', $kms->decrypt($ciphertext));
    }
}
