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

/**
 * A simple service that holds a resolved env var value.
 * Used in tests to force the application container's env var processor
 * to resolve and cache a vault-provided env var before the static loader
 * state is cleared.
 */
class VaultEnvVarPrimer
{
    public function __construct(public readonly string $value)
    {
    }
}
