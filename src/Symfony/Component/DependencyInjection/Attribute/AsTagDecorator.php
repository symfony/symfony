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
 * Declares a class as a decorator of all services with a specific tag.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsTagDecorator
{
    /**
     * @param string                          $tag       The tag name to decorate
     * @param int                             $priority  The priority of this decoration when multiple decorators are declared for the same tag
     * @param ContainerInterface::*_REFERENCE $onInvalid The behavior to adopt when no services have the tag; must be one of the {@see ContainerInterface} constants
     */
    public function __construct(
        public string $tag,
        public int $priority = 0,
        public int $onInvalid = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
    ) {
    }
}
