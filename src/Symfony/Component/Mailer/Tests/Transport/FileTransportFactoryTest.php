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

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FileTransport;
use Symfony\Component\Mailer\Transport\FileTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class FileTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new FileTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('file', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $tmpDir = sys_get_temp_dir();
        $dsn = new Dsn('file', 'null', null, null, null, [], $tmpDir.'/file.txt');
        yield [
            new Dsn('file', 'null', null, null, null, [], $tmpDir.'/file.txt'),
            new FileTransport($dsn, null, new NullLogger()),
        ];
    }

    public function testDirectoryNotFound(): void
    {
        $dir = sys_get_temp_dir().'/path/does/not/exist';
        $dsn = new Dsn('file', 'null', null, null, null, [], $dir.'/file.txt');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Directory $dir doesn't exist or is not writable");
        (new FileTransportFactory())->create($dsn);
    }
}
