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
 * One option named "debug" is supported; it toggles displaying errors
 * and defaults to the "APP_ENV" environment variable.
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
 *
 * @experimental in 5.3
 */
class GenericRuntime implements RuntimeInterface
{
    private $debug;

    /**
     * @param array {
     *   debug?: ?bool,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? true;

        if (!\is_bool($this->debug)) {
            $this->debug = filter_var($this->debug, \FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->debug) {
            $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
            $errorHandler = new BasicErrorHandler($this->debug);
            set_error_handler($errorHandler);
        } else {
            $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResolver(callable $callable): ResolverInterface
    {
        if (!$callable instanceof \Closure) {
            $callable = \Closure::fromCallable($callable);
        }

        $function = new \ReflectionFunction($callable);
        $parameters = $function->getParameters();

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

        if ($this->debug) {
            return new DebugClosureResolver($callable, $arguments);
        }

        return new ClosureResolver($callable, $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function getRunner(?object $application): RunnerInterface
    {
        if (null === $application) {
            $application = static function () { return 0; };
        }

        if ($application instanceof RunnerInterface) {
            return $application;
        }

        if (!\is_callable($application)) {
            throw new \LogicException(sprintf('"%s" doesn\'t know how to handle apps of type "%s".', get_debug_type($this), get_debug_type($application)));
        }

        if (!$application instanceof \Closure) {
            $application = \Closure::fromCallable($application);
        }

        if ($this->debug && ($r = new \ReflectionFunction($application)) && $r->getNumberOfRequiredParameters()) {
            throw new \ArgumentCountError(sprintf('Zero argument should be required by the runner callable, but at least one is in "%s" on line "%d.', $r->getFileName(), $r->getStartLine()));
        }

        return new ClosureRunner($application);
    }

    /**
     * @return mixed
     */
    protected function getArgument(\ReflectionParameter $parameter, ?string $type)
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

        $r = $parameter->getDeclaringFunction();

        throw new \InvalidArgumentException(sprintf('Cannot resolve argument "%s $%s" in "%s" on line "%d": "%s" supports only arguments "array $context", "array $argv" and "array $request".', $type, $parameter->name, $r->getFileName(), $r->getStartLine(), get_debug_type($this)));
    }
}
