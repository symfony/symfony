<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\EnvVarLoader;

use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;

/**
 * Simulates a vault-like env var loader whose secrets are populated via static
 * state. This allows tests to control what the loader returns and to verify
 * that the running container's already-initialized processor (with its cached
 * env var state) is used by ConfigDebugCommand instead of a freshly-built one.
 */
class StatefulEnvVarLoader implements EnvVarLoaderInterface
{
    private static array $envVars = [];

    public static function setEnvVar(string $name, string $value): void
    {
        self::$envVars[$name] = $value;
    }

    public static function reset(): void
    {
        self::$envVars = [];
    }

    public function loadEnvVars(): array
    {
        return self::$envVars;
    }
}
