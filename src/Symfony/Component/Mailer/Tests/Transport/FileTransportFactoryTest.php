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

use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FileTransport;
use Symfony\Component\Mailer\Transport\FileTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class FileTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new FileTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'null', null, null, null, ['path' => sys_get_temp_dir().'/symfony/emails']),
            true,
        ];

        yield [
            new Dsn('smtp', 'null'),
            false,
        ];

        yield [
            new Dsn('smtp', 'example.com'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        $path = sys_get_temp_dir().'/symfony/emails';

        yield [
            new Dsn('file', 'null', null, null, null, ['path' => $path]),
            new FileTransport($path, $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [new Dsn('foo', 'null')];
    }
}
