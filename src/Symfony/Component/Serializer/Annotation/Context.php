<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

// do not deprecate in 6.4/7.0, to make it easier for the ecosystem to support 6.4, 7.4 and 8.0 simultaneously

class_exists(\Symfony\Component\Serializer\Attribute\Context::class);

if (false) {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class Context extends \Symfony\Component\Serializer\Attribute\Context
    {
    }
}
