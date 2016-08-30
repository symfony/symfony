<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * Test that your code triggers expected error messages.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class ErrorAssert
{
    /**
     * @param string[]|string $expectedMessages Expected deprecation messages
     * @param callable        $testCode         A callable that is expected to trigger the deprecation messages
     */
    public static function assertDeprecationsAreTriggered($expectedMessages, $testCode)
    {
        if (!is_callable($testCode)) {
            throw new \InvalidArgumentException(sprintf('The code to be tested must be a valid callable ("%s" given).', gettype($testCode)));
        }

        self::assertErrorsAreTriggered(E_USER_DEPRECATED, $expectedMessages, $testCode);
    }

    /**
     * @param int             $expectedType     Expected triggered error type (pass one of PHP's E_* constants)
     * @param string[]|string $expectedMessages Expected error messages
     * @param callable        $testCode         A callable that is expected to trigger the error messages
     */
    public static function assertErrorsAreTriggered($expectedType, $expectedMessages, $testCode)
    {
        if (!is_callable($testCode)) {
            throw new \InvalidArgumentException(sprintf('The code to be tested must be a valid callable ("%s" given).', gettype($testCode)));
        }

        $e = null;
        $triggeredMessages = array();

        try {
            set_error_handler(function ($type, $message, $file, $line, $context) use ($expectedType, &$triggeredMessages, &$prevHandler) {
                if ($expectedType !== $type) {
                    return null !== $prevHandler && call_user_func($prevHandler, $type, $message, $file, $line, $context);
                }
                $triggeredMessages[] = $message;
            });

            call_user_func($testCode);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
        restore_error_handler();

        if (null !== $e) {
            throw $e;
        }

        $expectedMessages = (array) $expectedMessages;

        \PHPUnit_Framework_Assert::assertCount(count($expectedMessages), $triggeredMessages);

        foreach ($triggeredMessages as $i => $message) {
            \PHPUnit_Framework_Assert::assertContains($expectedMessages[$i], $message);
        }
    }
}
