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
 * Loops up to $loopMax times; pass 0 or a negative integer to loop indefinitely.
 *
 * When the application is an HttpKernelInterface and "FRANKENPHP_RESET_KERNEL" is truthy,
 * the kernel is cloned after each request to mitigate cross-request state leaks; subclasses
 * keeping non-resettable state should override __clone accordingly.
 *
 * "APP_RUNTIME_MODE" is set to "web=1&worker=1", or "web=1&worker=2" when FRANKENPHP_RESET_KERNEL
 * is active.
 *
 * Requests PHP cannot fully parse (post_max_size, max_input_vars, ...) are rejected with
 * a 400 response, without invoking the application.
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

        // PHP reports a request it cannot fully parse (post_max_size, max_input_vars, ...) as an E_WARNING
        // raised by frankenphp_handle_request() itself; an error handler that throws would then kill the
        // worker script. Mask warnings while waiting for a request and reject the request with a 400
        // response when one was recorded.
        $errorReporting = error_reporting();

        $handler = function () use ($server, $errorReporting, &$sfRequest, &$sfResponse): void {
            error_reporting($errorReporting);

            $lastError = error_get_last();
            if ($lastError && ($lastError['type'] & (\E_WARNING | \E_USER_WARNING)) && str_starts_with($lastError['message'], 'frankenphp_handle_request(')) {
                error_clear_last();
                // don't terminate() the previous request again
                $sfRequest = $sfResponse = null;

                (new Response('Bad Request', 400, ['Content-Type' => 'text/plain; charset=UTF-8']))->send();

                return;
            }

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
            error_reporting($errorReporting & ~\E_WARNING);

            $ret = frankenphp_handle_request($handler);

            error_reporting($errorReporting);
            error_clear_last();

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
