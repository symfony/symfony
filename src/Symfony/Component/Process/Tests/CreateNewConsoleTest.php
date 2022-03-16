<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @author Andrei Olteanu <andrei@flashsoft.eu>
 */
class CreateNewConsoleTest extends TestCase
{
    public function testOptionCreateNewConsole()
    {
        $this->expectNotToPerformAssertions();
        try {
            $process = new Process(['php', __DIR__.'/ThreeSecondProcess.php']);
            $process->setOptions(['create_new_console' => true]);
            $process->disableOutput();
            $process->start();
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    public function testItReturnsFastAfterStart()
    {
        // The started process must run in background after the main has finished but that can't be tested with PHPUnit
        $startTime = microtime(true);
        $process = new Process(['php', __DIR__.'/ThreeSecondProcess.php']);
        $process->setOptions(['create_new_console' => true]);
        $process->disableOutput();
        $process->start();
        $this->assertLessThan(3000, $startTime - microtime(true));
    }
}
