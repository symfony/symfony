<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Recipient;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class RecipientTest extends TestCase
{
    public function testCannotBeConstructedWithoutEmailAndWithoutPhone()
    {
        $this->expectException(InvalidArgumentException::class);

        new Recipient('', '');
    }

    /**
     * @dataProvider provideValidEmailAndPhone
     */
    public function testCanBeConstructed(string $email, string $phone)
    {
        $recipient = new Recipient($email, $phone);

        $this->assertSame($email, $recipient->getEmail());
        $this->assertSame($phone, $recipient->getPhone());
    }

    public static function provideValidEmailAndPhone()
    {
        yield ['test@test.de', '+0815'];
        yield ['test@test.de', ''];
        yield ['', '+0815'];
    }

    public function testEmailAndPhoneAreNotImmutable()
    {
        $recipient = new Recipient('test@test.de', '+0815');

        $this->assertSame('test@test.de', $recipient->getEmail());
        $this->assertSame('+0815', $recipient->getPhone());

        $recipient->email('test@test.com');
        $recipient->phone('+49815');

        $this->assertSame('test@test.com', $recipient->getEmail());
        $this->assertSame('+49815', $recipient->getPhone());
    }
}
