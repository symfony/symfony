<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Attribute;

/**
 * Registers a custom Doctrine DBAL type automatically.
 *
 * When used on a class extending {@see \Doctrine\DBAL\Types\Type} that is
 * registered as a service (i.e. present as a container definition), the type
 * will be registered without needing manual configuration in doctrine.yaml.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsDoctrineType
{
    /**
     * @param string|null $name The name under which the type is registered in Doctrine.
     *                          Defaults to the fully-qualified class name if not provided.
     */
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}
