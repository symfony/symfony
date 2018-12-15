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

use Symfony\Component\Config\Util\XmlUtils;
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
            'file' => 'string',
            'float' => 'float',
            'int' => 'int',
            'json' => 'array',
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
            return (bool) self::phpize($env);
        }

        if ('int' === $prefix) {
            if (!is_numeric($env = self::phpize($env))) {
                throw new RuntimeException(sprintf('Non-numeric env var "%s" cannot be cast to int.', $name));
            }

            return (int) $env;
        }

        if ('float' === $prefix) {
            if (!is_numeric($env = self::phpize($env))) {
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

            if (!\is_array($env)) {
                throw new RuntimeException(sprintf('Invalid JSON env var "%s": array expected, %s given.', $name, \gettype($env)));
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

        throw new RuntimeException(sprintf('Unsupported env var prefix "%s".', $prefix));
    }

    private static function phpize($value)
    {
        if (!class_exists(XmlUtils::class)) {
            throw new RuntimeException('The Symfony Config component is required to cast env vars to "bool", "int" or "float".');
        }

        return XmlUtils::phpize($value);
    }
}
