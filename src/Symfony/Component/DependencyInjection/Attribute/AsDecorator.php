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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Declares a decorating service.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsDecorator
{
    /**
     * @param string $decorates The service id to decorate
     * @param int    $priority  The priority of this decoration when multiple decorators are declared for the same service
     * @param int    $onInvalid The behavior to adopt when the decoration is invalid; must be one of the {@see ContainerInterface} constants
     */
    public function __construct(
        public string $decorates,
        public int $priority = 0,
        public int $onInvalid = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
    ) {
    }
}
