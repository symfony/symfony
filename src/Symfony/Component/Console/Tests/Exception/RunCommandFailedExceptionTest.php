<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RunCommandFailedException;
use Symfony\Component\Console\Messenger\RunCommandContext;
use Symfony\Component\Console\Messenger\RunCommandMessage;

class RunCommandFailedExceptionTest extends TestCase
{
    public function testDefaultExceptionProvidesCode()
    {
        $exception = self::createException(new \Exception('Boom!', 42));

        self::assertSame(42, $exception->getCode());
    }

    public function testNonIntegerCodeProvidesZero()
    {
        $exception = self::createException(new class extends \Exception {
            protected $code = 'non-integer-code';
        });

        self::assertSame(0, $exception->getCode());
    }

    private static function createException(\Throwable $inner): RunCommandFailedException
    {
        return new RunCommandFailedException(
            $inner,
            new RunCommandContext(
                new RunCommandMessage('foo'),
                exitCode: Command::FAILURE,
                output: 'bar'
            )
        );
    }
}
