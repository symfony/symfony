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
use Symfony\Component\Runtime\ResolvedApp\ClosureResolved;
use Symfony\Component\Runtime\ResolvedApp\ScalarResolved;
use Symfony\Component\Runtime\StartedApp\ClosureStarted;

// Help opcache.preload discover always-needed symbols
class_exists(ClosureResolved::class);
class_exists(BasicErrorHandler::class);

/**
 * A runtime to do bare-metal PHP without using superglobals.
 *
 * One option named "debug" is supported; it toggles displaying errors.
 *
 * The app-closure returned by the entry script must return either:
 * - "string" to echo the response content, or
 * - "int" to set the exit status code.
 *
 * The app-closure can declare arguments among either:
 * - "array $context" to get a local array similar to $_SERVER;
 * - "array $argv" to get the command line arguments when running on the CLI;
 * - "array $request" to get a local array with keys "query", "data", "files" and
 *   "session", which map to $_GET, $_POST, $FILES and &$_SESSION respectively.
 *
 * The runtime sets up a strict error handler that throws
 * exceptions when a PHP warning/notice is raised.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class BaseRuntime implements RuntimeInterface
{
    private $debug;

    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? true;
        $errorHandler = new BasicErrorHandler($this->debug);
        set_error_handler($errorHandler);
        set_exception_handler([$errorHandler, 'handleException']);
    }

    public function resolve(\Closure $app): ResolvedAppInterface
    {
        $arguments = [];
        $function = new \ReflectionFunction($app);

        try {
            foreach ($function->getParameters() as $parameter) {
                $arguments[] = $this->getArgument($parameter, $parameter->getType());
            }
        } catch (\InvalidArgumentException $e) {
            if (!$parameter->isOptional()) {
                throw $e;
            }
        }

        $returnType = $function->getReturnType();

        switch ($returnType instanceof \ReflectionNamedType ? $returnType->getName() : '') {
            case 'string':
                return new ScalarResolved(static function () use ($app, $arguments): int {
                    echo $app(...$arguments);

                    return 0;
                });

            case 'int':
            case 'void':
                return new ScalarResolved(static function () use ($app, $arguments): int {
                    return $app(...$arguments) ?? 0;
                });
        }

        return new ClosureResolved($app, $arguments);
    }

    public function start(object $app): StartedAppInterface
    {
        if (!$app instanceof \Closure) {
            throw new \LogicException(sprintf('"%s" doesn\'t know how to handle apps of type "%s".', get_debug_type($this), get_debug_type($app)));
        }

        if ($this->debug && (new \ReflectionFunction($app))->getNumberOfRequiredParameters()) {
            throw new \ArgumentCountError('Zero argument should be required by the closure returned by the app, but at least one is.');
        }

        return new ClosureStarted($app);
    }

    protected function getArgument(\ReflectionParameter $parameter, ?\ReflectionType $type)
    {
        $type = $type instanceof \ReflectionNamedType ? $type->getName() : '';

        if (RuntimeInterface::class === $type) {
            return $this;
        }

        if ('array' !== $type) {
            throw new \InvalidArgumentException(sprintf('Cannot resolve argument "%s $%s".', $type, $parameter->name));
        }

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
                    'data' => $_POST,
                    'files' => $_FILES,
                    'session' => &$_SESSION,
                ];
        }

        throw new \InvalidArgumentException(sprintf('Cannot resolve array argument "$%s", did you mean "$context" or "$request"?', $parameter->name));
    }
}
