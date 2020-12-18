<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Transport\Dsn;

final class DsnTest extends TestCase
{
    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(string $string, Dsn $expectedDsn)
    {
        $actualDsn = Dsn::fromString($string);

        $this->assertSame($expectedDsn->getScheme(), $actualDsn->getScheme());
        $this->assertSame($expectedDsn->getHost(), $actualDsn->getHost());
        $this->assertSame($expectedDsn->getPort(), $actualDsn->getPort());
        $this->assertSame($expectedDsn->getUser(), $actualDsn->getUser());
        $this->assertSame($expectedDsn->getPassword(), $actualDsn->getPassword());
        $this->assertSame($expectedDsn->getPath(), $actualDsn->getPath());
        $this->assertSame($expectedDsn->getOption('from'), $actualDsn->getOption('from'));

        $this->assertSame($string, $actualDsn->getOriginalDsn());
    }

    public function fromStringProvider(): iterable
    {
        yield 'simple dsn' => [
            'scheme://localhost',
            new Dsn('scheme', 'localhost', null, null, null, [], null),
        ];

        yield 'simple dsn including @ sign, but no user/password/token' => [
            'scheme://@localhost',
            new Dsn('scheme', 'localhost', null, null),
        ];

        yield 'simple dsn including : sign and @ sign, but no user/password/token' => [
            'scheme://:@localhost',
            new Dsn('scheme', 'localhost', null, null),
        ];

        yield 'simple dsn including user, : sign and @ sign, but no password' => [
            'scheme://user1:@localhost',
            new Dsn('scheme', 'localhost', 'user1', null),
        ];

        yield 'simple dsn including : sign, password, and @ sign, but no user' => [
            'scheme://:pass@localhost',
            new Dsn('scheme', 'localhost', null, 'pass'),
        ];

        yield 'dsn with user and pass' => [
            'scheme://u$er:pa$s@localhost',
            new Dsn('scheme', 'localhost', 'u$er', 'pa$s', null, [], null),
        ];

        yield 'dsn with user and pass and custom port' => [
            'scheme://u$er:pa$s@localhost:8000',
            new Dsn('scheme', 'localhost', 'u$er', 'pa$s', '8000', [], null),
        ];

        yield 'dsn with user and pass, custom port and custom path' => [
            'scheme://u$er:pa$s@localhost:8000/channel',
            new Dsn('scheme', 'localhost', 'u$er', 'pa$s', '8000', [], '/channel'),
        ];

        yield 'dsn with user and pass, custom port, custom path and custom options' => [
            'scheme://u$er:pa$s@localhost:8000/channel?from=FROM',
            new Dsn('scheme', 'localhost', 'u$er', 'pa$s', '8000', ['from' => 'FROM'], '/channel'),
        ];
    }

    /**
     * @dataProvider invalidDsnProvider
     */
    public function testInvalidDsn(string $dsn, string $exceptionMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);
        Dsn::fromString($dsn);
    }

    public function invalidDsnProvider(): iterable
    {
        yield [
            'some://',
            'The "some://" notifier DSN is invalid.',
        ];

        yield [
            '//slack',
            'The "//slack" notifier DSN must contain a scheme.',
        ];

        yield [
            'file:///some/path',
            'The "file:///some/path" notifier DSN must contain a host (use "default" by default).',
        ];
    }

    public function testGetOption()
    {
        $options = ['with_value' => 'some value', 'nullable' => null];
        $dsn = new Dsn('scheme', 'localhost', 'u$er', 'pa$s', '8000', $options, '/channel');

        $this->assertSame('some value', $dsn->getOption('with_value'));
        $this->assertSame('default', $dsn->getOption('nullable', 'default'));
        $this->assertSame('default', $dsn->getOption('not_existent_property', 'default'));
    }
}
