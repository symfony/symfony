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
 * An attribute to tell how a base type should be autoconfigured.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Autoconfigure
{
    /**
     * @param array<array-key, array<array-key, mixed>>|string[]|null $tags         The tags to add to the service
     * @param array<array<mixed>>|null                                $calls        The calls to be made when instantiating the service
     * @param array<string, mixed>|null                               $bind         The bindings to declare for the service
     * @param bool|string|null                                        $lazy         Whether the service is lazy-loaded
     * @param bool|null                                               $public       Whether to declare the service as public
     * @param bool|null                                               $shared       Whether to declare the service as shared
     * @param bool|null                                               $autowire     Whether to declare the service as autowired
     * @param array<string, mixed>|null                               $properties   The properties to define when creating the service
     * @param array{string, string}|string|null                       $configurator A PHP function, reference or an array containing a class/reference and a method to call after the service is fully initialized
     * @param string|null                                             $constructor  The public static method to use to instantiate the service
     */
    public function __construct(
        public ?array $tags = null,
        public ?array $calls = null,
        public ?array $bind = null,
        public bool|string|null $lazy = null,
        public ?bool $public = null,
        public ?bool $shared = null,
        public ?bool $autowire = null,
        public ?array $properties = null,
        public array|string|null $configurator = null,
        public ?string $constructor = null,
    ) {
    }
}
