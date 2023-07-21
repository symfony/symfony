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

/**
 * Annotation class for @Version().
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Olivier Michaud <olivier@micoli.org>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
final class Version
{
    public function __construct()
    {
    }
}
