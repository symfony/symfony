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
 * Annotation class for @MaxDepth().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class MaxDepth
{
    /**
     * @var int
     */
    private $maxDepth;

    /**
     * @param int|array $maxDepth
     */
    public function __construct($maxDepth)
    {
        if (\is_array($maxDepth)) {
            if (!isset($maxDepth['value'])) {
                throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" should be set.', static::class));
            }
            $maxDepth = $maxDepth['value'];
        }

        if (!\is_int($maxDepth) || $maxDepth <= 0) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a positive integer.', static::class));
        }

        $this->maxDepth = $maxDepth;
    }

    public function getMaxDepth()
    {
        return $this->maxDepth;
    }
}
