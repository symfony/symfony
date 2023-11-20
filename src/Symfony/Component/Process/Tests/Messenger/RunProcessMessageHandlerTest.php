<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\RunProcessFailedException;
use Symfony\Component\Process\Messenger\RunProcessMessage;
use Symfony\Component\Process\Messenger\RunProcessMessageHandler;

class RunProcessMessageHandlerTest extends TestCase
{
    public function testRunSuccessfulProcess()
    {
        $context = (new RunProcessMessageHandler())(new RunProcessMessage(['ls'], cwd: __DIR__));

        $this->assertSame(['ls'], $context->message->command);
        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString(basename(__FILE__), $context->output);
    }

    public function testRunFailedProcess()
    {
        try {
            (new RunProcessMessageHandler())(new RunProcessMessage(['invalid']));
        } catch (RunProcessFailedException $e) {
            $this->assertSame(['invalid'], $e->context->message->command);
            $this->assertSame('\\' === \DIRECTORY_SEPARATOR ? 1 : 127, $e->context->exitCode);

            return;
        }

        $this->fail('Exception not thrown');
    }
}
