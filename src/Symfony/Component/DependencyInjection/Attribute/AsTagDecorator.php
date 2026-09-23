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
     * @param int|null                        $priority  The priority of this decoration; null lets "within"/"around" decide it, else they only reorder the decorators that share it
     * @param ContainerInterface::*_REFERENCE $onInvalid The behavior to adopt when no services have the tag; must be one of the {@see ContainerInterface} constants
     * @param string|list<string>|null        $within    Decorators of the tagged services that wrap this one, as service ids or classes
     * @param string|list<string>|null        $around    Decorators of the tagged services that this one wraps, as service ids or classes
     */
    public function __construct(
        public string $tag,
        public ?int $priority = null,
        public int $onInvalid = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        public string|array|null $within = null,
        public string|array|null $around = null,
    ) {
    }
}
