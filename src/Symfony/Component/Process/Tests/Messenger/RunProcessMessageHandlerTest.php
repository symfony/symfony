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
            $this->assertContains(
                $e->context->exitCode,
                [null, '\\' === \DIRECTORY_SEPARATOR ? 1 : 127],
                'Exit code should be 1 on Windows, 127 on other systems, or null',
            );

            return;
        }

        $this->fail('Exception not thrown');
    }

    public function testRunSuccessfulProcessFromShellCommandline()
    {
        $context = (new RunProcessMessageHandler())(RunProcessMessage::fromShellCommandline('ls | grep Test', cwd: __DIR__));

        $this->assertSame('ls | grep Test', $context->message->commandLine);
        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString(basename(__FILE__), $context->output);
    }

    public function testRunFailedProcessFromShellCommandline()
    {
        try {
            (new RunProcessMessageHandler())(RunProcessMessage::fromShellCommandline('invalid'));
            $this->fail('Exception not thrown');
        } catch (RunProcessFailedException $e) {
            $this->assertSame('invalid', $e->context->message->commandLine);
            $this->assertContains(
                $e->context->exitCode,
                [null, '\\' === \DIRECTORY_SEPARATOR ? 1 : 127],
                'Exit code should be 1 on Windows, 127 on other systems, or null',
            );
        }
    }
}
