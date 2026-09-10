<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp\Stream;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

class AbstractStreamTest extends TestCase
{
    /**
     * @dataProvider provideReplace
     */
    public function testReplace(string $expected, string $from, string $to, array $chunks)
    {
        $result = '';
        foreach (AbstractStream::replace($from, $to, $chunks) as $chunk) {
            $result .= $chunk;
        }

        $this->assertSame($expected, $result);
    }

    public function testHasPendingData()
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $client = stream_socket_client(stream_socket_get_name($listener, false));
        $server = stream_socket_accept($listener);
        $stream = new class($client) extends AbstractStream {
            public function __construct($out)
            {
                $this->out = $out;
            }

            public function initialize(): void
            {
            }

            protected function getReadConnectionDescription(): string
            {
                return 'pair';
            }
        };

        $this->assertFalse($stream->hasPendingData());

        fwrite($server, "250 2.0.0 OK\r\n421 4.4.1 Connection timed out\r\n");
        usleep(10000);

        $this->assertTrue($stream->hasPendingData());
        $this->assertSame("250 2.0.0 OK\r\n", $stream->readLine());
        $this->assertTrue($stream->hasPendingData(), 'The unsolicited reply is still pending.');
        $this->assertSame("421 4.4.1 Connection timed out\r\n", $stream->readLine());

        fclose($server);
        fclose($client);
        fclose($listener);
    }

    public static function provideReplace()
    {
        yield ['ca', 'ab', 'c', ['a', 'b', 'a']];
        yield ['ac', 'ab', 'c', ['a', 'ab']];
        yield ['cbc', 'aba', 'c', ['ababa', 'ba']];
    }
}
