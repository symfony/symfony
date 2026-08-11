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
 * An attribute to register this class as a service only when the given class or interface does not exist.
 *
 * This is typically used to register a fallback implementation when an optional package is not installed.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class WhenClassMissing
{
    /**
     * @param string       $class          The class or interface that must not exist for this service to be registered
     * @param string|null  $package        When set, the Composer package that provides the class; the condition then
     *                                     also matches when the package is only installed as a dev dependency and
     *                                     would be missing after "composer install --no-dev" (inverse semantics of
     *                                     ContainerBuilder::willBeAvailable())
     * @param list<string> $parentPackages The packages the annotated service belongs to; a dev-only $package is
     *                                     still considered available when one of these is a dev dependency too
     */
    public function __construct(
        public string $class,
        public ?string $package = null,
        public array $parentPackages = [],
    ) {
    }
}
