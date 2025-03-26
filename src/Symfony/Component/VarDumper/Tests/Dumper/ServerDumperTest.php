<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\Dumper\ServerDumper;

class ServerDumperTest extends TestCase
{
    private const VAR_DUMPER_SERVER = 'tcp://127.0.0.1:9913';

    public function testDumpForwardsToWrappedDumperWhenServerIsUnavailable()
    {
        $wrappedDumper = $this->createMock(DataDumperInterface::class);

        $dumper = new ServerDumper(self::VAR_DUMPER_SERVER, $wrappedDumper);

        $cloner = new VarCloner();
        $data = $cloner->cloneVar('foo');

        $wrappedDumper->expects($this->once())->method('dump')->with($data);

        $dumper->dump($data);
    }

    public function testDump()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Skip transient test on Windows');
        }

        $wrappedDumper = $this->createMock(DataDumperInterface::class);
        $wrappedDumper->expects($this->never())->method('dump'); // test wrapped dumper is not used

        $cloner = new VarCloner();
        $data = $cloner->cloneVar('foo');
        $dumper = new ServerDumper(self::VAR_DUMPER_SERVER, $wrappedDumper, [
            'foo_provider' => new class implements ContextProviderInterface {
                public function getContext(): ?array
                {
                    return ['foo'];
                }
            },
        ]);

        $dumped = null;
        $process = $this->getServerProcess();
        $process->start(function ($type, $buffer) use ($process, &$dumped, $dumper, $data) {
            if (Process::ERR === $type) {
                $process->stop();
                $this->fail();
            } elseif ("READY\n" === $buffer) {
                $dumper->dump($data);
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

    private function getServerProcess(): Process
    {
        $process = new PhpProcess(file_get_contents(__DIR__.'/../Fixtures/dump_server.php'), null, [
            'COMPONENT_ROOT' => __DIR__.'/../../',
            'VAR_DUMPER_SERVER' => self::VAR_DUMPER_SERVER,
        ]);

        return $process->setTimeout('\\' === \DIRECTORY_SEPARATOR ? 19 : 9);
    }
}
