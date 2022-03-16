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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;

/**
 * @requires extension sodium
 */
class SodiumMarshallerTest extends TestCase
{
    private $decryptionKey;

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
        $defaultResult = $defaultMarshaller->marshall($values, $failed);

        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($sodiumResult['a']));
        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($defaultResult['a']));

        $sodiumMarshaller = new SodiumMarshaller([sodium_crypto_box_keypair(), $this->decryptionKey], $defaultMarshaller);

        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($sodiumResult['a']));
        $this->assertSame($values['a'], $sodiumMarshaller->unmarshall($defaultResult['a']));
    }
}
