<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Group;

class GroupTest extends TestCase
{
    public function testConstructor()
    {
        $g = new Group('Friends', [new Address('fabien@symfony.com'), 'helene@symfony.com']);
        $this->assertSame('Friends', $g->getName());
        $this->assertEquals([new Address('fabien@symfony.com'), new Address('helene@symfony.com')], $g->getAddresses());
    }

    public function testConstructorWithNoAddress()
    {
        $g = new Group('undisclosed-recipients');
        $this->assertSame('undisclosed-recipients', $g->getName());
        $this->assertSame([], $g->getAddresses());
    }

    public function testConstructorWithDisplayNameString()
    {
        $g = new Group('Friends', ['Fabien <fabien@symfony.com>']);
        $this->assertSame('Fabien', $g->getAddresses()[0]->getName());
    }

    public function testConstructorWithInvalidAddress()
    {
        $this->expectException(RfcComplianceException::class);
        new Group('Friends', ['fab   pot@symfony.com']);
    }

    #[DataProvider('provideEmptyNames')]
    public function testConstructorWithEmptyName(string $name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A group must have a display name.');
        new Group($name);
    }

    public static function provideEmptyNames(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'control characters only' => ["\r\n\x00"];
    }

    public function testConstructorStripsControlCharactersFromName()
    {
        $g = new Group("a\x00b");
        $this->assertSame('ab', $g->getName());
    }
}
