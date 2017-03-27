<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Fill\Gradient;

use Symfony\Component\Image\Image\PointInterface;

/**
 * Vertical gradient fill
 */
final class Vertical extends Linear
{
    /**
     * {@inheritdoc}
     */
    public function getDistance(PointInterface $position)
    {
        return $position->getY();
    }
}
