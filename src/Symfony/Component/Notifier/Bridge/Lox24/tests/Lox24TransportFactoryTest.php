<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24TransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24TransportFactoryTest extends TestCase
{
    public function testUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('The "unsupported" scheme is not supported; supported schemes for notifier "unsupported" are: "lox24".');
        $factory = new Lox24TransportFactory();
        $factory->create(new Dsn('unsupported://user:authToken@default?from=0611223344'));
    }

    public function testCompleteDsn(): void
    {
        $dsn = 'lox24://123456:aaaabbbbbbccccccdddddeeee@default?from=sender&type=voice&voice_language=en&is_text_delete=1&callback_data=CALLBACK_DATA';
        $transport = (new Lox24TransportFactory())->create(new Dsn($dsn));

        $this->assertSame('lox24://api.lox24.eu?from=sender&type=voice&voice_language=en&is_text_delete=1&callback_data=CALLBACK_DATA', (string)$transport);
    }


}
