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
 * An attribute to register this class as a service only when the given class or interface exists.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class WhenClassExists
{
    /**
     * @param string       $class          The class or interface that must exist for this service to be registered
     * @param string|null  $package        When set, the Composer package that provides the class; the condition then
     *                                     also fails when the package is only installed as a dev dependency and would
     *                                     be missing after "composer install --no-dev" (same semantics as
     *                                     ContainerBuilder::willBeAvailable())
     * @param list<string> $parentPackages The packages the annotated service belongs to; a dev-only $package is
     *                                     accepted when one of these is a dev dependency too
     */
    public function __construct(
        public string $class,
        public ?string $package = null,
        public array $parentPackages = [],
    ) {
    }
}
