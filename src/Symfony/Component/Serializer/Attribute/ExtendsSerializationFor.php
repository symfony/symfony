<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Attribute;

/**
 * Declares that serialization attributes listed on the current class should be added to the given class.
 *
 * Classes that use this attribute should contain only properties and methods that
 * exist on the target class (not necessarily all of them).
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExtendsSerializationFor
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
    ) {
    }
}
