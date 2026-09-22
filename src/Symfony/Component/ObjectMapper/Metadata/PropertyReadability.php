<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * The part of a property's readability that only depends on its declaration.
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class PropertyReadability
{
    public const ALWAYS = 0;
    public const NEVER = 1;
    /** Readable once initialized, or when unset() re-enabled magic methods on it. */
    public const INITIALIZED = 2;
    /** Not declared: readable when the instance defines it. */
    public const DYNAMIC = 3;
    /** Delegated to the configured PropertyAccessor. */
    public const ACCESSOR = 4;

    private function __construct()
    {
    }
}
