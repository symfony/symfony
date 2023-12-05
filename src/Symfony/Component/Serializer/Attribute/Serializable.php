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
 * Classes with this attribute will get a custom normalizer to improve speed when
 * serializing/deserializing.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Serializable
{
}

if (!class_exists(\Symfony\Component\Serializer\Annotation\Serializable::class, false)) {
    class_alias(Serializable::class, \Symfony\Component\Serializer\Annotation\Serializable::class);
}
