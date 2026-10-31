<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Runtime\Tests\FrankenPhpWorkerRunnerTest;

if (!function_exists('frankenphp_handle_request')) {
    function frankenphp_handle_request(callable $callable): bool
    {
        // FrankenPHP returns false without calling the handler when the worker stops
        if (null !== FrankenPhpWorkerRunnerTest::$requests && 0 >= FrankenPhpWorkerRunnerTest::$requests--) {
            return false;
        }

        FrankenPhpWorkerRunnerTest::$handlingRequest = true;
        try {
            $callable();
        } finally {
            FrankenPhpWorkerRunnerTest::$handlingRequest = false;
        }

        return null !== FrankenPhpWorkerRunnerTest::$requests;
    }
}
