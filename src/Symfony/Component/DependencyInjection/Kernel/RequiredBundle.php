<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Kernel;

/**
 * Declares a bundle dependency that should be auto-registered.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class RequiredBundle
{
    /**
     * @param class-string<BundleInterface> $class           The bundle class to require
     * @param bool                          $ignoreOnInvalid Whether to silently skip if the class doesn't exist
     */
    public function __construct(
        public string $class,
        public bool $ignoreOnInvalid = false,
    ) {
    }
}
