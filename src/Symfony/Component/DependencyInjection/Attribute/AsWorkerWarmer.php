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
 * Registers a method to call once a long-running worker booted its kernel, before it handles requests.
 *
 * Use it to open connections or to load data in memory. As the method runs outside of any request,
 * it must not rely on request-scoped state. What it stores in services tagged with "kernel.reset"
 * is lost when they are reset after the first request.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsWorkerWarmer
{
    /**
     * @param string|null $method The method to call, defaults to "__invoke" or to the method the attribute is put on
     */
    public function __construct(
        public ?string $method = null,
    ) {
    }
}
