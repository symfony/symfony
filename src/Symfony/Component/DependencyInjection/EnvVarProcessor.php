<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvVarProcessor implements EnvVarProcessorInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getProvidedTypes()
    {
        return [
            'base64' => 'string',
            'bool' => 'bool',
            'const' => 'bool|int|float|string|array',
            'csv' => 'array',
            'file' => 'string',
            'float' => 'float',
            'int' => 'int',
            'json' => 'array',
            'key' => 'bool|int|float|string|array',
            'resolve' => 'string',
            'string' => 'string',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getEnv($prefix, $name, \Closure $getEnv)
    {
        $i = strpos($name, ':');

        if ('key' === $prefix) {
            if (false === $i) {
                throw new RuntimeException(sprintf('Invalid configuration: env var "key:%s" does not contain a key specifier.', $name));
            }

            $next = substr($name, $i + 1);
            $key = substr($name, 0, $i);
            $array = $getEnv($next);

            if (!\is_array($array)) {
                throw new RuntimeException(sprintf('Resolved value of "%s" did not result in an array value.', $next));
            }
            if (!array_key_exists($key, $array)) {
                throw new RuntimeException(sprintf('Key "%s" not found in "%s" (resolved from "%s")', $key, json_encode($array), $next));
            }

            return $array[$key];
        }

        if ('file' === $prefix) {
            if (!is_scalar($file = $getEnv($name))) {
                throw new RuntimeException(sprintf('Invalid file name: env var "%s" is non-scalar.', $name));
            }
            if (!file_exists($file)) {
                throw new RuntimeException(sprintf('Env "file:%s" not found: %s does not exist.', $name, $file));
            }

            return file_get_contents($file);
        }

        if (false !== $i || 'string' !== $prefix) {
            if (null === $env = $getEnv($name)) {
                return;
            }
        } elseif (isset($_ENV[$name])) {
            $env = $_ENV[$name];
        } elseif (isset($_SERVER[$name]) && 0 !== strpos($name, 'HTTP_')) {
            $env = $_SERVER[$name];
        } elseif (false === ($env = getenv($name)) || null === $env) { // null is a possible value because of thread safety issues
            if (!$this->container->hasParameter("env($name)")) {
                throw new EnvNotFoundException($name);
            }

            if (null === $env = $this->container->getParameter("env($name)")) {
                return;
            }
        }

        if (!is_scalar($env)) {
            throw new RuntimeException(sprintf('Non-scalar env var "%s" cannot be cast to %s.', $name, $prefix));
        }

        if ('string' === $prefix) {
            return (string) $env;
        }

        if ('bool' === $prefix) {
            return (bool) (filter_var($env, FILTER_VALIDATE_BOOLEAN) ?: filter_var($env, FILTER_VALIDATE_INT) ?: filter_var($env, FILTER_VALIDATE_FLOAT));
        }

        if ('int' === $prefix) {
            if (false === $env = filter_var($env, FILTER_VALIDATE_INT) ?: filter_var($env, FILTER_VALIDATE_FLOAT)) {
                throw new RuntimeException(sprintf('Non-numeric env var "%s" cannot be cast to int.', $name));
            }

            return (int) $env;
        }

        if ('float' === $prefix) {
            if (false === $env = filter_var($env, FILTER_VALIDATE_FLOAT)) {
                throw new RuntimeException(sprintf('Non-numeric env var "%s" cannot be cast to float.', $name));
            }

            return (float) $env;
        }

        if ('const' === $prefix) {
            if (!\defined($env)) {
                throw new RuntimeException(sprintf('Env var "%s" maps to undefined constant "%s".', $name, $env));
            }

            return \constant($env);
        }

        if ('base64' === $prefix) {
            return base64_decode($env);
        }

        if ('json' === $prefix) {
            $env = json_decode($env, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new RuntimeException(sprintf('Invalid JSON in env var "%s": '.json_last_error_msg(), $name));
            }

            if (null !== $env && !\is_array($env)) {
                throw new RuntimeException(sprintf('Invalid JSON env var "%s": array or null expected, %s given.', $name, \gettype($env)));
            }

            return $env;
        }

        if ('resolve' === $prefix) {
            return preg_replace_callback('/%%|%([^%\s]+)%/', function ($match) use ($name) {
                if (!isset($match[1])) {
                    return '%';
                }
                $value = $this->container->getParameter($match[1]);
                if (!is_scalar($value)) {
                    throw new RuntimeException(sprintf('Parameter "%s" found when resolving env var "%s" must be scalar, "%s" given.', $match[1], $name, \gettype($value)));
                }

                return $value;
            }, $env);
        }

        if ('csv' === $prefix) {
            return str_getcsv($env);
        }

        throw new RuntimeException(sprintf('Unsupported env var prefix "%s".', $prefix));
    }
}
