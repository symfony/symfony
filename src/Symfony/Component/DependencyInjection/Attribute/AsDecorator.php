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
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsDecorator
{
    /**
     * @param string                   $decorates The service id to decorate
     * @param int|null                 $priority  The priority of this decoration; null lets "within"/"around" decide it, else they only reorder the decorators that share it
     * @param int                      $onInvalid The behavior to adopt when the decoration is invalid; must be one of the {@see ContainerInterface} constants
     * @param string|list<string>|null $within    Decorators of the same service that wrap this one, as service ids or classes
     * @param string|list<string>|null $around    Decorators of the same service that this one wraps, as service ids or classes
     */
    public function __construct(
        public string $decorates,
        public ?int $priority = null,
        public int $onInvalid = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        public string|array|null $within = null,
        public string|array|null $around = null,
    ) {
    }
}
