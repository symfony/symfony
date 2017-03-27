<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Point;

use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Point as OriginalPoint;
use Symfony\Component\Image\Image\PointInterface;

/**
 * Point center
 */
final class Center implements PointInterface
{
    /**
     * @var BoxInterface
     */
    private $box;

    /**
     * Constructs coordinate with size instance, it needs to be relative to
     *
     * @param BoxInterface $box
     */
    public function __construct(BoxInterface $box)
    {
        $this->box = $box;
    }

    /**
     * {@inheritdoc}
     */
    public function getX()
    {
        return ceil($this->box->getWidth() / 2);
    }

    /**
     * {@inheritdoc}
     */
    public function getY()
    {
        return ceil($this->box->getHeight() / 2);
    }

    /**
     * {@inheritdoc}
     */
    public function in(BoxInterface $box)
    {
        return $this->getX() < $box->getWidth() && $this->getY() < $box->getHeight();
    }

    /**
     * {@inheritdoc}
     */
    public function move($amount)
    {
        return new OriginalPoint($this->getX() + $amount, $this->getY() + $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return sprintf('(%d, %d)', $this->getX(), $this->getY());
    }
}
