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
 * An attribute to expose a public method of a service as a service of its own.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class AsCallable
{
    /**
     * @param array<array<mixed>>|string[] $tags   The tags to add to the service; a tag's attributes may be a \Closure or a [class-string, method] callable that computes them from the class-string of the service declaring the method
     * @param string|null                  $id     The id of the service, defaulting to "<service id>::<method>"
     * @param bool|class-string            $lazy   Whether to instantiate the service declaring the method only when the closure is first called (ignored for static methods), or the single-method interface to implement instead of returning a \Closure, which is always lazy
     * @param string|null                  $target The name of the autowiring alias to register for the service, to be used with #[Target]
     */
    public function __construct(
        public array $tags = [],
        public ?string $id = null,
        public bool|string $lazy = true,
        public ?string $target = null,
    ) {
    }
}
