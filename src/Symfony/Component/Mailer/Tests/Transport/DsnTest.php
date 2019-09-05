<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Transport\Dsn;

class DsnTest extends TestCase
{
    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(string $string, Dsn $dsn): void
    {
        $this->assertEquals($dsn, Dsn::fromString($string));
    }

    public function testGetOption(): void
    {
        $options = ['with_value' => 'some value', 'nullable' => null];
        $dsn = new Dsn('smtp', 'example.com', null, null, null, $options);

        $this->assertSame('some value', $dsn->getOption('with_value'));
        $this->assertSame('default', $dsn->getOption('nullable', 'default'));
        $this->assertSame('default', $dsn->getOption('not_existent_property', 'default'));
    }

    /**
     * @dataProvider invalidDsnProvider
     */
    public function testInvalidDsn(string $dsn, string $exceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);
        Dsn::fromString($dsn);
    }

    public function fromStringProvider(): iterable
    {
        yield 'simple smtp without user and pass' => [
            'smtp://example.com',
            new Dsn('smtp', 'example.com'),
        ];

        yield 'simple smtp with custom port' => [
            'smtp://user1:pass2@example.com:99',
            new Dsn('smtp', 'example.com', 'user1', 'pass2', 99),
        ];

        yield 'gmail smtp with urlencoded user and pass' => [
            'smtp://u%24er:pa%24s@gmail',
            new Dsn('smtp', 'gmail', 'u$er', 'pa$s'),
        ];

        yield 'mailgun api with custom options' => [
            'api://u%24er:pa%24s@mailgun?region=eu',
            new Dsn('api', 'mailgun', 'u$er', 'pa$s', null, ['region' => 'eu']),
        ];
    }

    public function invalidDsnProvider(): iterable
    {
        yield [
            'some://',
            'The "some://" mailer DSN is invalid.',
        ];

        yield [
            '//sendmail',
            'The "//sendmail" mailer DSN must contain a scheme.',
        ];

        yield [
            'file:///some/path',
            'The "file:///some/path" mailer DSN must contain a host (use "default" by default).',
        ];
    }
}
