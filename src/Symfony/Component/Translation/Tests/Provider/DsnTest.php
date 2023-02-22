<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\MissingRequiredOptionException;
use Symfony\Component\Translation\Provider\Dsn;

final class DsnTest extends TestCase
{
    /**
     * @dataProvider constructProvider
     */
    public function testConstruct(string $dsnString, string $scheme, string $host, string $user = null, string $password = null, int $port = null, array $options = [], string $path = null)
    {
        $dsn = new Dsn($dsnString);
        $this->assertSame($dsnString, $dsn->getOriginalDsn());

        $this->assertSame($scheme, $dsn->getScheme());
        $this->assertSame($host, $dsn->getHost());
        $this->assertSame($user, $dsn->getUser());
        $this->assertSame($password, $dsn->getPassword());
        $this->assertSame($port, $dsn->getPort());
        $this->assertSame($path, $dsn->getPath());
        $this->assertSame($options, $dsn->getOptions());
    }

    public static function constructProvider(): iterable
    {
        yield 'simple dsn' => [
            'scheme://localhost',
            'scheme',
            'localhost',
        ];

        yield 'simple dsn including @ sign, but no user/password/token' => [
            'scheme://@localhost',
            'scheme',
            'localhost',
        ];

        yield 'simple dsn including : sign and @ sign, but no user/password/token' => [
            'scheme://:@localhost',
            'scheme',
            'localhost',
        ];

        yield 'simple dsn including user, : sign and @ sign, but no password' => [
            'scheme://user1:@localhost',
            'scheme',
            'localhost',
            'user1',
        ];

        yield 'simple dsn including : sign, password, and @ sign, but no user' => [
            'scheme://:pass@localhost',
            'scheme',
            'localhost',
            null,
            'pass',
        ];

        yield 'dsn with user and pass' => [
            'scheme://u$er:pa$s@localhost',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
        ];

        yield 'dsn with user and pass and custom port' => [
            'scheme://u$er:pa$s@localhost:8000',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
            8000,
        ];

        yield 'dsn with user and pass, custom port and custom path' => [
            'scheme://u$er:pa$s@localhost:8000/channel',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
            8000,
            [],
            '/channel',
        ];

        yield 'dsn with user and pass, custom port, custom path and custom option' => [
            'scheme://u$er:pa$s@localhost:8000/channel?from=FROM',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
            8000,
            [
                'from' => 'FROM',
            ],
            '/channel',
        ];

        yield 'dsn with user and pass, custom port, custom path and custom options' => [
            'scheme://u$er:pa$s@localhost:8000/channel?from=FROM&to=TO',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
            8000,
            [
                'from' => 'FROM',
                'to' => 'TO',
            ],
            '/channel',
        ];

        yield 'dsn with user and pass, custom port, custom path and custom options and custom options keep the same order' => [
            'scheme://u$er:pa$s@localhost:8000/channel?to=TO&from=FROM',
            'scheme',
            'localhost',
            'u$er',
            'pa$s',
            8000,
            [
                'to' => 'TO',
                'from' => 'FROM',
            ],
            '/channel',
        ];
    }

    /**
     * @dataProvider invalidDsnProvider
     */
    public function testInvalidDsn(string $dsnString, string $exceptionMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Dsn($dsnString);
    }

    public static function invalidDsnProvider(): iterable
    {
        yield [
            'some://',
            'The translation provider DSN is invalid.',
        ];

        yield [
            '//loco',
            'The translation provider DSN must contain a scheme.',
        ];

        yield [
            'file:///some/path',
            'The translation provider DSN must contain a host (use "default" by default).',
        ];
    }

    /**
     * @dataProvider getOptionProvider
     */
    public function testGetOption($expected, string $dsnString, string $option, string $default = null)
    {
        $dsn = new Dsn($dsnString);

        $this->assertSame($expected, $dsn->getOption($option, $default));
    }

    public static function getOptionProvider(): iterable
    {
        yield [
            'foo',
            'scheme://localhost?with_value=foo',
            'with_value',
        ];

        yield [
            '',
            'scheme://localhost?empty=',
            'empty',
        ];

        yield [
            '0',
            'scheme://localhost?zero=0',
            'zero',
        ];

        yield [
            'default-value',
            'scheme://localhost?option=value',
            'non_existent_property',
            'default-value',
        ];
    }

    /**
     * @dataProvider getRequiredOptionProvider
     */
    public function testGetRequiredOption(string $expectedValue, string $options, string $option)
    {
        $dsn = new Dsn(sprintf('scheme://localhost?%s', $options));

        $this->assertSame($expectedValue, $dsn->getRequiredOption($option));
    }

    public static function getRequiredOptionProvider(): iterable
    {
        yield [
            'value',
            'with_value=value',
            'with_value',
        ];

        yield [
            '0',
            'timeout=0',
            'timeout',
        ];
    }

    /**
     * @dataProvider getRequiredOptionThrowsMissingRequiredOptionExceptionProvider
     */
    public function testGetRequiredOptionThrowsMissingRequiredOptionException(string $expectedExceptionMessage, string $options, string $option)
    {
        $dsn = new Dsn(sprintf('scheme://localhost?%s', $options));

        $this->expectException(MissingRequiredOptionException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $dsn->getRequiredOption($option);
    }

    public static function getRequiredOptionThrowsMissingRequiredOptionExceptionProvider(): iterable
    {
        yield [
            'The option "foo_bar" is required but missing.',
            'with_value=value',
            'foo_bar',
        ];

        yield [
            'The option "with_empty_string" is required but missing.',
            'with_empty_string=',
            'with_empty_string',
        ];
    }
}
