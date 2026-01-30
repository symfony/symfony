<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\JsonPath;

class JsonPathTest extends TestCase
{
    public function testBuildPath(): void
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->index(0)
            ->key('address');

        $this->assertSame('$["users"][0]["address"]', (string) $path);
        $this->assertSame('$["users"][0]["address"]..["city"]', (string) $path->deepScan()->key('city'));
    }

    public function testBuildWithFilter(): void
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->filter('@.age > 18');

        $this->assertSame('$["users"][?(@.age > 18)]', (string) $path);
    }

    public function testAll(): void
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->all();

        $this->assertSame('$["users"][*]', (string) $path);
    }

    public function testFirst(): void
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->first();

        $this->assertSame('$["users"][0]', (string) $path);
    }

    public function testLast(): void
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->last();

        $this->assertSame('$["users"][-1]', (string) $path);
    }

    #[DataProvider('provideKeysToEscape')]
    public function testEscapedKey(string $key, string $expectedPath): void
    {
        $path = new JsonPath();
        $path = $path->key($key);

        $this->assertSame($expectedPath, (string) $path);
    }

    public static function provideKeysToEscape(): iterable
    {
        yield ['simple_key', '$["simple_key"]'];
        yield ['key"with"quotes', '$["key\\"with\\"quotes"]'];
        yield ['path\\backslash', '$["path\\backslash"]'];
        yield ['mixed\\"case', '$["mixed\\\\\\"case"]'];
        yield ['unicode_🔑', '$["unicode_🔑"]'];
        yield ['"quotes_only"', '$["\\"quotes_only\\""]'];
        yield ['\\\\multiple\\\\backslashes', '$["\\\\\\\\multiple\\\\\\backslashes"]'];
        yield ["control\x00\x1f\x1echar", '$["control\u0000\u001f\u001echar"]'];

        yield ['key"with\\"mixed', '$["key\\"with\\\\\\"mixed"]'];
        yield ['\\"complex\\"case\\"', '$["\\\\\\"complex\\\\\\"case\\\\\\""]'];
        yield ['json_like":{"value":"test"}', '$["json_like\\":{\\"value\\":\\"test\\"}"]'];
        yield ['C:\\Program Files\\"App Name"', '$["C:\\\\Program Files\\\\\\"App Name\\""]'];

        yield ['key_with_é_accents', '$["key_with_é_accents"]'];
        yield ['unicode_→_arrows', '$["unicode_→_arrows"]'];
        yield ['chinese_中文_key', '$["chinese_中文_key"]'];

        yield ['', '$[""]'];
        yield [' ', '$[" "]'];
        yield ['   spaces   ', '$["   spaces   "]'];
        yield ["\t\n\r", '$["\\t\\n\\r"]'];
        yield ["control\x00char", '$["control\u0000char"]'];
        yield ["newline\nkey", '$["newline\\nkey"]'];
        yield ["tab\tkey", '$["tab\\tkey"]'];
        yield ["carriage\rreturn", '$["carriage\\rreturn"]'];
    }
}
