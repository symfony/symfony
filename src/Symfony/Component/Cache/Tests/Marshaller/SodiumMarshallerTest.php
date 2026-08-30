<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;

#[RequiresPhpExtension('sodium')]
class SodiumMarshallerTest extends TestCase
{
    private string $decryptionKey;

    protected function setUp(): void
    {
        $this->decryptionKey = sodium_crypto_box_keypair();
    }

    public function testMarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $defaultMarshaller);

        $values = ['a' => '123'];
        $failed = [];
        $defaultResult = $defaultMarshaller->marshall($values, $failed);

        $sodiumResult = $sodiumMarshaller->marshall($values, $failed);
        $sodiumResult['a'] = sodium_crypto_box_seal_open($sodiumResult['a'], $this->decryptionKey);

        $this->assertSame($defaultResult, $sodiumResult);
    }

    public function testUnmarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $defaultMarshaller);

        $values = ['a' => '123'];
        $failed = [];

        $sodiumResult = $sodiumMarshaller->marshall($values, $failed);

        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($sodiumResult['a']));

        $sodiumMarshaller = new SodiumMarshaller([sodium_crypto_box_keypair(), $this->decryptionKey], $defaultMarshaller);

        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($sodiumResult['a']));
    }

    public function testUnmarshallThrowsOnUnencryptedValues()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $defaultMarshaller);

        $failed = [];
        $defaultResult = $defaultMarshaller->marshall(['a' => '123'], $failed);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Failed to decrypt value.');

        $sodiumMarshaller->unmarshall($defaultResult['a']);
    }

    public function testUnmarshallThrowsWhenNoKeyMatches()
    {
        $defaultMarshaller = new DefaultMarshaller();

        $failed = [];
        $otherResult = (new SodiumMarshaller([sodium_crypto_box_keypair()], $defaultMarshaller))->marshall(['a' => '123'], $failed);

        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $defaultMarshaller);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Failed to decrypt value.');

        $sodiumMarshaller->unmarshall($otherResult['a']);
    }

    public function testUnmarshallDoesNotForwardUndecryptedValues()
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->never())->method('unmarshall');

        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $marshaller);

        $this->expectException(\DomainException::class);

        $sodiumMarshaller->unmarshall(serialize(new \ArrayObject(['a' => '123'])));
    }

    public function testUnmarshallForwardsEmptyValues()
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())->method('unmarshall')->with('')->willReturn('');

        $sodiumMarshaller = new SodiumMarshaller([$this->decryptionKey], $marshaller);

        $this->assertSame('', $sodiumMarshaller->unmarshall(''));
    }
}
