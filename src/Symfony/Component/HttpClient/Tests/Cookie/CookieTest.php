<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Cookie;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Cookie\Cookie;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;

class CookieTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $cookie = new Cookie('flavor', 'chocolate');

        $this->assertSame('flavor', $cookie->getName());
        $this->assertSame('chocolate', $cookie->getValue());
    }

    public function testDefaultValueIsEmpty(): void
    {
        $cookie = new Cookie('session');

        $this->assertSame('', $cookie->getValue());
    }

    public function testToString(): void
    {
        $cookie = new Cookie('flavor', 'chocolate');

        $this->assertSame('flavor=chocolate', (string) $cookie);
    }

    public function testToStringWithEmptyValue(): void
    {
        $this->assertSame('session=', (string) new Cookie('session'));
    }

    public function testFromString(): void
    {
        $cookie = Cookie::fromString('flavor=chocolate');

        $this->assertSame('flavor', $cookie->getName());
        $this->assertSame('chocolate', $cookie->getValue());
    }

    public function testFromStringWithSpaces(): void
    {
        $cookie = Cookie::fromString('  flavor = chocolate  ');

        $this->assertSame('flavor', $cookie->getName());
        $this->assertSame('chocolate', $cookie->getValue());
    }

    public function testFromStringWithEqualsInValue(): void
    {
        $cookie = Cookie::fromString('token=abc=def==');

        $this->assertSame('token', $cookie->getName());
        $this->assertSame('abc=def==', $cookie->getValue());
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name cannot be empty');

        new Cookie('');
    }

    public function testInvalidNameCharactersThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('illegal characters');

        new Cookie('bad name');
    }

    public function testInvalidNameWithSemicolon(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie('bad;name');
    }

    /**
     * @dataProvider provideInvalidCookieNames
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideInvalidCookieNames')]
    public function testInvalidCookieNameThrows(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie($name);
    }

    public static function provideInvalidCookieNames(): iterable
    {
        yield 'space' => ['bad name'];
        yield 'semicolon' => ['bad;name'];
        yield 'double-quote' => ['bad"name'];
        yield 'slash' => ['bad/name'];
        yield 'colon' => ['bad:name'];
        yield 'equals' => ['bad=name'];
        yield 'at-sign' => ['bad@name'];
        yield 'backslash' => ['bad\\name'];
        yield 'open-bracket' => ['bad[name'];
        yield 'open-paren' => ['bad(name'];
        yield 'DEL (0x7F)' => ["bad\x7fname"];
        // CTLs that were previously not caught (0x01–0x08, 0x0B, 0x0C, 0x0E–0x1F)
        yield 'CTL 0x01' => ["\x01name"];
        yield 'CTL 0x08 (BS)' => ["\x08name"];
        yield 'CTL 0x0B (VT)' => ["\x0Bname"];
        yield 'CTL 0x0C (FF)' => ["\x0Cname"];
        yield 'CTL 0x0E (SO)' => ["\x0Ename"];
        yield 'CTL 0x1F (US)' => ["\x1Fname"];
    }

    public function testGetIllegalNameCharactersReturnsSameInstance(): void
    {
        $this->assertSame(Cookie::getIllegalNameCharacters(), Cookie::getIllegalNameCharacters());
    }

    public function testGetIllegalNameCharactersCoversFullCTLRange(): void
    {
        $illegal = Cookie::getIllegalNameCharacters();

        // All CTLs 0–31 must be present
        for ($i = 0; $i <= 31; ++$i) {
            $this->assertStringContainsString(chr($i), $illegal, \sprintf('CTL 0x%02X must be illegal', $i));
        }

        // Space and DEL
        $this->assertStringContainsString(' ', $illegal);
        $this->assertStringContainsString("\x7F", $illegal);

        // Key separators
        foreach (['"', '(', ')', ',', '/', ':', ';', '<', '=', '>', '?', '@', '[', '\\', ']', '{', '}'] as $sep) {
            $this->assertStringContainsString($sep, $illegal, \sprintf('Separator "%s" must be illegal', $sep));
        }
    }

    public function testInvalidValueWithCrLfThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie('name', "value\r\n");
    }

    /**
     * @dataProvider provideValidCookieValues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideValidCookieValues')]
    public function testValidCookieValue(string $value): void
    {
        $cookie = new Cookie('name', $value);
        $this->assertSame($value, $cookie->getValue());
    }

    public static function provideValidCookieValues(): iterable
    {
        yield 'empty' => [''];
        yield 'simple word' => ['chocolate'];
        yield 'digits' => ['12345'];
        yield 'base64 chars' => ['abc+def/ghi='];
        yield 'url-encoded' => ['hello%20world'];
        yield 'equals sign in value' => ['abc=def=='];
        yield 'printable specials' => ['!#$%&\'()*+-./:'];
        yield 'angle brackets and query' => ['<=>?@'];
        yield 'brackets and tilde' => ['[]^_`{|}~'];
        yield 'long value' => [str_repeat('a', 4096)];
    }

    /**
     * @dataProvider provideInvalidCookieValues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideInvalidCookieValues')]
    public function testInvalidCookieValueThrows(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie('name', $value);
    }

    public static function provideInvalidCookieValues(): iterable
    {
        yield 'semicolon' => ['foo;bar'];
        yield 'space' => ['foo bar'];
        yield 'comma' => ['foo,bar'];
        yield 'backslash' => ['foo\\bar'];
        yield 'double-quote' => ['foo"bar'];
        yield 'CR' => ["foo\rbar"];
        yield 'LF' => ["foo\nbar"];
        yield 'NUL' => ["foo\0bar"];
        yield 'tab' => ["foo\tbar"];
        yield 'DEL (0x7F)' => ["foo\x7Fbar"];
        yield 'non-ASCII byte' => ["\xC3\xA9"]; // UTF-8 "é"
        yield 'control char 0x01' => ["\x01"];
        yield 'control char 0x1F' => ["\x1F"];
    }

    public function testGetIllegalValueCharactersReturnsSameInstance(): void
    {
        $this->assertSame(Cookie::getIllegalValueCharacters(), Cookie::getIllegalValueCharacters());
    }

    public function testGetIllegalValueCharactersCoversAllInvalidChars(): void
    {
        $illegal = Cookie::getIllegalValueCharacters();

        // All CTLs 0–31 and space must be present
        for ($i = 0; $i <= 32; ++$i) {
            $this->assertStringContainsString(chr($i), $illegal, \sprintf('CTL 0x%02X must be illegal in values', $i));
        }

        // Specific separators illegal in values
        foreach (['"', ',', ';', '\\'] as $sep) {
            $this->assertStringContainsString($sep, $illegal, \sprintf('"%s" must be illegal in values', $sep));
        }

        // DEL
        $this->assertStringContainsString("\x7F", $illegal);

        // Non-ASCII spot checks
        $this->assertStringContainsString("\x80", $illegal);
        $this->assertStringContainsString("\xFF", $illegal);
    }

    public function testImplementsStringable(): void
    {
        $this->assertInstanceOf(\Stringable::class, new Cookie('name', 'value'));
    }
}
