<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

/**
 * Declares a method as the factory for a service.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class AsFactory
{
    /**
     * @param string|null $id The service id, defaulting to the method's return type
     */
    public function __construct(
        public ?string $id = null,
    ) {
    }
}
