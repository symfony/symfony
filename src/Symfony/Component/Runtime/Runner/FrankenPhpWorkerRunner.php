<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * A runner for FrankenPHP in worker mode.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class FrankenPhpWorkerRunner implements RunnerInterface
{
    public function __construct(
        private HttpKernelInterface|Response $application,
        private int $loopMax,
    ) {
    }

    public function run(): int
    {
        // Prevent worker script termination when a client connection is interrupted
        ignore_user_abort(true);

        $server = array_filter($_SERVER, static fn (string $key) => !str_starts_with($key, 'HTTP_'), \ARRAY_FILTER_USE_KEY);
        $resetKernel = $this->application instanceof HttpKernelInterface && filter_var($server['FRANKENPHP_RESET_KERNEL'] ?? false, \FILTER_VALIDATE_BOOL);
        $server['APP_RUNTIME_MODE'] = $resetKernel ? 'web=1&worker=2' : 'web=1&worker=1';

        $handler = function () use ($server, &$sfRequest, &$sfResponse): void {
            // Connect to the Xdebug client if it's available
            if (\extension_loaded('xdebug') && \function_exists('xdebug_connect_to_client')) {
                xdebug_connect_to_client();
            }

            // Merge the environment variables coming from DotEnv with the ones tied to the current request
            $_SERVER += $server;

            if ($this->application instanceof HttpKernelInterface) {
                $sfRequest = Request::createFromGlobals();
                $sfResponse = $this->application->handle($sfRequest);
            } else {
                $sfResponse = $this->application;
            }

            $sfResponse->send();
        };

        $loops = 0;
        do {
            $ret = frankenphp_handle_request($handler);

            if ($this->application instanceof TerminableInterface && $sfRequest && $sfResponse) {
                $this->application->terminate($sfRequest, $sfResponse);
            }
            if ($resetKernel) {
                $this->application = clone $this->application;
            }

            gc_collect_cycles();
        } while ($ret && (0 >= $this->loopMax || ++$loops < $this->loopMax));

        return 0;
    }
}
