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

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @Embedded().
 *
 * @Annotation
 * @Target({"PROPERTY"})
 *
 * @author Jibé Barth <barth.jib@gmail.com>
 */
final class Embedded
{
    public function __construct(array $data)
    {
        if (!empty($data['value'])) {
            throw new InvalidArgumentException(sprintf('Annotation "%s" doesn\'t accept parameters.', \get_class($this)));
        }
    }
}
