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

class StaticEnvVarLoader implements EnvVarLoaderInterface
{
    private array $envVars;

    public function __construct(private EnvVarLoaderInterface $envVarLoader)
    {
    }

    public function loadEnvVars(): array
    {
        return $this->envVars ??= $this->envVarLoader->loadEnvVars();
    }
}
