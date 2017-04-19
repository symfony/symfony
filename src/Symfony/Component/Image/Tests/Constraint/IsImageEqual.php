<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Util\InvalidArgumentHelper;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Histogram\Bucket;
use Symfony\Component\Image\Image\Histogram\Range;

if (class_exists(\PHPUnit_Framework_Constraint::class)) {
    abstract class PHPUnitConstraint extends \PHPUnit_Framework_Constraint
    {
    }
} else {
    abstract class PHPUnitConstraint extends Constraint
    {
    }
}

if (class_exists(\PHPUnit_Util_InvalidArgumentHelper::class)) {
    abstract class PHPUnitInvalidArgumentHelper extends \PHPUnit_Util_InvalidArgumentHelper
    {
    }
} else {
    abstract class PHPUnitInvalidArgumentHelper extends InvalidArgumentHelper
    {
    }
}

class IsImageEqual extends PHPUnitConstraint
{
    /**
     * @var \Symfony\Component\Image\Image\ImageInterface
     */
    private $value;

    /**
     * @var float
     */
    private $delta;

    /**
     * @var int
     */
    private $buckets;

    /**
     * @param \Symfony\Component\Image\Image\ImageInterface $value
     * @param float                                         $delta
     * @param int                                           $buckets
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value, $delta = 0.1, $buckets = 4)
    {
        if (!$value instanceof ImageInterface) {
            throw PHPUnitInvalidArgumentHelper::factory(1, ImageInterface::class);
        }

        if (!is_numeric($delta)) {
            throw PHPUnitInvalidArgumentHelper::factory(2, 'numeric');
        }

        if (!is_integer($buckets) || $buckets <= 0) {
            throw PHPUnitInvalidArgumentHelper::factory(3, 'integer');
        }

        $this->value = $value;
        $this->delta = $delta;
        $this->buckets = $buckets;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($other, $description = '', $returnResult = false)
    {
        if (!$other instanceof ImageInterface) {
            throw PHPUnitInvalidArgumentHelper::factory(1, ImageInterface::class);
        }

        list($currentRed, $currentGreen, $currentBlue, $currentAlpha) = $this->normalize($this->value);
        list($otherRed, $otherGreen, $otherBlue, $otherAlpha) = $this->normalize($other);

        $total = 0;

        foreach ($currentRed as $bucket => $count) {
            $total += abs($count - $otherRed[$bucket]);
        }

        foreach ($currentGreen as $bucket => $count) {
            $total += abs($count - $otherGreen[$bucket]);
        }

        foreach ($currentBlue as $bucket => $count) {
            $total += abs($count - $otherBlue[$bucket]);
        }

        foreach ($currentAlpha as $bucket => $count) {
            $total += abs($count - $otherAlpha[$bucket]);
        }

        return $total <= $this->delta;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return sprintf('contains color histogram identical to expected %s', \PHPUnit_Util_Type::toString($this->value));
    }

    /**
     * @param \Symfony\Component\Image\Image\ImageInterface $image
     *
     * @return array
     */
    private function normalize(ImageInterface $image)
    {
        $step = (int) round(255 / $this->buckets);

        $red =
        $green =
        $blue =
        $alpha = array();

        for ($i = 1; $i <= $this->buckets; ++$i) {
            $range = new Range(($i - 1) * $step, $i * $step);
            $red[] = new Bucket($range);
            $green[] = new Bucket($range);
            $blue[] = new Bucket($range);
            $alpha[] = new Bucket($range);
        }

        foreach ($image->histogram() as $color) {
            foreach ($red as $bucket) {
                $bucket->add($color->getRed());
            }

            foreach ($green as $bucket) {
                $bucket->add($color->getGreen());
            }

            foreach ($blue as $bucket) {
                $bucket->add($color->getBlue());
            }

            foreach ($alpha as $bucket) {
                $bucket->add($color->getAlpha());
            }
        }

        $total = $image->getSize()->square();

        $callback = function (Bucket $bucket) use ($total) {
            return count($bucket) / $total;
        };

        return array(
            array_map($callback, $red),
            array_map($callback, $green),
            array_map($callback, $blue),
            array_map($callback, $alpha),
        );
    }
}
