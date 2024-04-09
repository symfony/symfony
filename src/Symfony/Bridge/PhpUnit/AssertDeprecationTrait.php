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

use PHPUnit\Framework\Assert;

trait AssertDeprecationTrait
{
    /**
     * @param callable(): TReturn $callable
     * @return TReturn
     * @template TReturn
     */
    private static function assertDeprecation(string $expectedMessage, callable $callable): mixed
    {
        $matched = false;
        $observed = [];
        $previousErrorHandler = null;
        $previousErrorHandler = set_error_handler(static function (int $type, string $message) use (&$previousErrorHandler, &$matched, &$observed, $expectedMessage) {
            if (($type === E_USER_DEPRECATED || $type === E_DEPRECATED)) {
                if (str_contains($message, $expectedMessage)) {
                    return $matched = true;
                }

                $observed[] = $message;
            }

            return $previousErrorHandler(...func_get_args());
        }) ?? static function () {
            return false;
        };
        try {
            $result = $callable();
        } finally {
            restore_error_handler();
        }

        if ([] === $observed) {
            Assert::assertTrue($matched, implode(PHP_EOL, [
                'The following deprecation has not been raised: ' . $expectedMessage,
                'No other deprecations have been observed.',
            ]));
        } else {
            Assert::assertTrue($matched, implode(PHP_EOL, array_merge(
                [
                    'The following deprecation has not been raised: ' . $expectedMessage,
                    'Instead, the following deprecations have been observed:',
                ],
                array_map(static function (string $message) {
                    return " - $message";
                }, $observed),
            )));
        }

        return $result;
    }
}
