<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\StaticEnvVarLoader;

class StaticEnvVarLoaderTest extends TestCase
{
    public function testLoadEnvVarsCachesInnerLoaderEnvVars()
    {
        $innerLoader = new class(['FOO' => 'BAR']) implements EnvVarLoaderInterface {
            /** @param array<string, string> */
            public function __construct(public array $envVars = [])
            {
            }

            public function loadEnvVars(): array
            {
                return $this->envVars;
            }
        };

        $loader = new StaticEnvVarLoader($innerLoader);
        $this->assertSame(['FOO' => 'BAR'], $loader->loadEnvVars());

        $innerLoader->envVars = ['BAR' => 'BAZ'];
        $this->assertSame(['FOO' => 'BAR'], $loader->loadEnvVars());
    }
}
