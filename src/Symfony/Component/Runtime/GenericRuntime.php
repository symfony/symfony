<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime;

use Symfony\Component\Runtime\Internal\BasicErrorHandler;
use Symfony\Component\Runtime\Resolver\ClosureResolver;
use Symfony\Component\Runtime\Resolver\DebugClosureResolver;
use Symfony\Component\Runtime\Runner\ClosureRunner;

// Help opcache.preload discover always-needed symbols
class_exists(ClosureResolver::class);

/**
 * A runtime to do bare-metal PHP without using superglobals.
 *
 * It supports the following options:
 *  - "debug" toggles displaying errors and defaults
 *    to the "APP_DEBUG" environment variable;
 *  - "runtimes" maps types to a GenericRuntime implementation
 *    that knows how to deal with each of them;
 *  - "error_handler" defines the class to use to handle PHP errors;
 *  - "env_var_name" and "debug_var_name" define the name of the env
 *    vars that hold the Symfony env and the debug flag respectively.
 *
 * The app-callable can declare arguments among either:
 * - "array $context" to get a local array similar to $_SERVER;
 * - "array $argv" to get the command line arguments when running on the CLI;
 * - "array $request" to get a local array with keys "query", "body", "files" and
 *   "session", which map to $_GET, $_POST, $FILES and &$_SESSION respectively.
 *
 * It should return a Closure():int|string|null or an instance of RunnerInterface.
 *
 * In debug mode, the runtime registers a strict error handler
 * that throws exceptions when a PHP warning/notice is raised.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class GenericRuntime implements RuntimeInterface
{
    protected $options;

    /**
     * @param array {
     *   debug?: ?bool,
     *   runtimes?: ?array,
     *   error_handler?: string|false,
     *   env_var_name?: string,
     *   debug_var_name?: string,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $options['env_var_name'] ??= 'APP_ENV';
        $debugKey = $options['debug_var_name'] ??= 'APP_DEBUG';

        $debug = $options['debug'] ?? $_SERVER[$debugKey] ?? $_ENV[$debugKey] ?? true;

        if (!\is_bool($debug)) {
            $debug = filter_var($debug, \FILTER_VALIDATE_BOOL);
        }

        if ($debug) {
            umask(0000);
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '1';

            if (false !== $errorHandler = ($options['error_handler'] ?? BasicErrorHandler::class)) {
                $errorHandler::register($debug);
                $options['error_handler'] = false;
            }
        } else {
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '0';
        }

        $this->options = $options;
    }

    public function getResolver(callable $callable, \ReflectionFunction $reflector = null): ResolverInterface
    {
        $callable = $callable(...);
        $parameters = ($reflector ?? new \ReflectionFunction($callable))->getParameters();
        $arguments = function () use ($parameters) {
            $arguments = [];

            try {
                foreach ($parameters as $parameter) {
                    $type = $parameter->getType();
                    $arguments[] = $this->getArgument($parameter, $type instanceof \ReflectionNamedType ? $type->getName() : null);
                }
            } catch (\InvalidArgumentException $e) {
                if (!$parameter->isOptional()) {
                    throw $e;
                }
            }

            return $arguments;
        };

        if ($_SERVER[$this->options['debug_var_name']]) {
            return new DebugClosureResolver($callable, $arguments);
        }

        return new ClosureResolver($callable, $arguments);
    }

    public function getRunner(?object $application): RunnerInterface
    {
        $application ??= static function () { return 0; };

        if ($application instanceof RunnerInterface) {
            return $application;
        }

        if (!$application instanceof \Closure) {
            if ($runtime = $this->resolveRuntime($application::class)) {
                return $runtime->getRunner($application);
            }

            if (!\is_callable($application)) {
                throw new \LogicException(sprintf('"%s" doesn\'t know how to handle apps of type "%s".', get_debug_type($this), get_debug_type($application)));
            }

            $application = $application(...);
        }

        if ($_SERVER[$this->options['debug_var_name']] && ($r = new \ReflectionFunction($application)) && $r->getNumberOfRequiredParameters()) {
            throw new \ArgumentCountError(sprintf('Zero argument should be required by the runner callable, but at least one is in "%s" on line "%d.', $r->getFileName(), $r->getStartLine()));
        }

        return new ClosureRunner($application);
    }

    protected function getArgument(\ReflectionParameter $parameter, ?string $type): mixed
    {
        if ('array' === $type) {
            switch ($parameter->name) {
                case 'context':
                    $context = $_SERVER;

                    if ($_ENV && !isset($_SERVER['PATH']) && !isset($_SERVER['Path'])) {
                        $context += $_ENV;
                    }

                    return $context;

                case 'argv':
                    return $_SERVER['argv'] ?? [];

                case 'request':
                    return [
                        'query' => $_GET,
                        'body' => $_POST,
                        'files' => $_FILES,
                        'session' => &$_SESSION,
                    ];
            }
        }

        if (RuntimeInterface::class === $type) {
            return $this;
        }

        if (!$runtime = $this->getRuntime($type)) {
            $r = $parameter->getDeclaringFunction();

            throw new \InvalidArgumentException(sprintf('Cannot resolve argument "%s $%s" in "%s" on line "%d": "%s" supports only arguments "array $context", "array $argv" and "array $request", or a runtime named "Symfony\Runtime\%1$sRuntime".', $type, $parameter->name, $r->getFileName(), $r->getStartLine(), get_debug_type($this)));
        }

        return $runtime->getArgument($parameter, $type);
    }

    protected static function register(self $runtime): self
    {
        return $runtime;
    }

    private function getRuntime(string $type): ?self
    {
        if (null === $runtime = ($this->options['runtimes'][$type] ?? null)) {
            $runtime = 'Symfony\Runtime\\'.$type.'Runtime';
            $runtime = class_exists($runtime) ? $runtime : $this->options['runtimes'][$type] = false;
        }

        if (\is_string($runtime)) {
            $runtime = $runtime::register($this);
        }

        if ($this === $runtime) {
            return null;
        }

        return $runtime ?: null;
    }

    private function resolveRuntime(string $class): ?self
    {
        if ($runtime = $this->getRuntime($class)) {
            return $runtime;
        }

        foreach (class_parents($class) as $type) {
            if ($runtime = $this->getRuntime($type)) {
                return $runtime;
            }
        }

        foreach (class_implements($class) as $type) {
            if ($runtime = $this->getRuntime($type)) {
                return $runtime;
            }
        }

        return null;
    }
}
