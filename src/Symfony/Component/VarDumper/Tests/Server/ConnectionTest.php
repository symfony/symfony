<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;
use Symfony\Component\VarDumper\Server\Connection;

class ConnectionTest extends TestCase
{
    private const VAR_DUMPER_SERVER = 'tcp://127.0.0.1:9913';

    public function testDump()
    {
        $cloner = new VarCloner();
        $data = $cloner->cloneVar('foo');
        $connection = new Connection(self::VAR_DUMPER_SERVER, [
            'foo_provider' => new class() implements ContextProviderInterface {
                public function getContext(): ?array
                {
                    return ['foo'];
                }
            },
        ]);

        $dumped = null;
        $process = $this->getServerProcess();
        $process->start(function ($type, $buffer) use ($process, &$dumped, $connection, $data) {
            if (Process::ERR === $type) {
                $process->stop();
                $this->fail();
            } elseif ("READY\n" === $buffer) {
                $connection->write($data);
            } else {
                $dumped .= $buffer;
            }
        });

        $process->wait();

        $this->assertTrue($process->isSuccessful());
        $this->assertStringMatchesFormat(<<<'DUMP'
(3) "foo"
[
  "timestamp" => %d.%d
  "foo_provider" => [
    (3) "foo"
  ]
]
%d

DUMP
        , $dumped);
    }

    public function testNoServer()
    {
        $cloner = new VarCloner();
        $data = $cloner->cloneVar('foo');
        $connection = new Connection(self::VAR_DUMPER_SERVER);
        $start = microtime(true);
        $this->assertFalse($connection->write($data));
        $this->assertLessThan(4, microtime(true) - $start);
    }

    private function getServerProcess(): Process
    {
        $process = new PhpProcess(file_get_contents(__DIR__.'/../Fixtures/dump_server.php'), null, [
            'COMPONENT_ROOT' => __DIR__.'/../../',
            'VAR_DUMPER_SERVER' => self::VAR_DUMPER_SERVER,
        ]);

        return $process->setTimeout(9);
    }
}
