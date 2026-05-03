<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\ErrorHandler\ErrorHandler;

class FrameworkBundleTest extends TestCase
{
    /**
     * Regression test for #63693: when symfony/runtime is not present,
     * FrameworkBundle::boot() registered an ErrorHandler via
     * ErrorHandler::register(null, false) but never restored it. PHPUnit
     * then flagged the test as risky with "Test code or tested code did
     * not remove its own exception handlers".
     */
    public function testShutdownRestoresErrorAndExceptionHandlersRegisteredInBoot()
    {
        $initialErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $initialExceptionHandler = set_exception_handler(static fn () => null);
        restore_exception_handler();

        $bundle = new FrameworkBundle();

        // Reproduce the no-runtime branch of FrameworkBundle::boot() without
        // wiring a container: register an ErrorHandler and bind it on the
        // bundle's private property the same way boot() does.
        $registered = ErrorHandler::register(null, false);
        (new \ReflectionProperty(FrameworkBundle::class, 'registeredErrorHandler'))->setValue($bundle, $registered);

        $afterRegisterError = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $this->assertNotSame($initialErrorHandler, $afterRegisterError, 'Sanity check: ErrorHandler::register() must change the active error handler.');

        $bundle->shutdown();

        $afterShutdownError = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $afterShutdownException = set_exception_handler(static fn () => null);
        restore_exception_handler();

        $this->assertSame($initialErrorHandler, $afterShutdownError, 'shutdown() must restore the error handler that was active before boot() registered ours.');
        $this->assertSame($initialExceptionHandler, $afterShutdownException, 'shutdown() must restore the exception handler that was active before boot() registered ours.');
    }

    public function testShutdownIsNoOpWhenBootDidNotRegisterHandler()
    {
        $initialErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        // No registration — simulates the SymfonyRuntime branch of boot() where
        // FrameworkBundle does not own the handler stack.
        (new FrameworkBundle())->shutdown();

        $afterShutdownError = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        $this->assertSame($initialErrorHandler, $afterShutdownError);
    }

    public function testShutdownPreservesHandlerStackedAboveOurs()
    {
        $initialErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $initialExceptionHandler = set_exception_handler(static fn () => null);
        restore_exception_handler();

        $bundle = new FrameworkBundle();

        $registered = ErrorHandler::register(null, false);
        (new \ReflectionProperty(FrameworkBundle::class, 'registeredErrorHandler'))->setValue($bundle, $registered);

        // Another component pushes its own error handler on top of ours.
        $foreign = static fn (): bool => false;
        set_error_handler($foreign);

        $bundle->shutdown();

        try {
            // Foreign handler must still be on top — shutdown() only restores when
            // our handler is the one currently active.
            $current = set_error_handler(static fn (): bool => false);
            restore_error_handler();
            $this->assertSame($foreign, $current, 'shutdown() must not pop a handler stacked above ours.');

            // shutdown() did pop our exception handler since nothing was stacked
            // above it; the exception stack is therefore back to its initial state.
            $currentException = set_exception_handler(static fn () => null);
            restore_exception_handler();
            $this->assertSame($initialExceptionHandler, $currentException);
        } finally {
            // Restore the error stack ourselves: pop $foreign, then pop $registered
            // that shutdown() deliberately left in place. Runs even on assertion
            // failure so the next test starts from a clean stack.
            restore_error_handler();
            restore_error_handler();
        }
    }
}
