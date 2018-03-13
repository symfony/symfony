<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class NumberToStringTransformer implements DataTransformerInterface
{
    /**
     * @var bool
     */
    private $forceFullScale;

    /**
     * @var int|null
     */
    private $scale;

    /**
     * @param bool $forceFullScale
     * @param int|null $scale
     */
    public function __construct($forceFullScale = false, $scale = null)
    {
        $this->forceFullScale = $forceFullScale;
        $this->scale = $scale;
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $valueIsInt = is_int($value);
        if (!$valueIsInt && !is_float($value)) {
            throw new TransformationFailedException('Expected an int or a float.');
        }

        if ($this->forceFullScale && is_int($this->scale)) {
            if ($valueIsInt) {
                $value = floatval($value);
            }

            return number_format($value, $this->scale, '.', '');
        }

        try {
            return (string) $value;
        } catch (\Exception $e) {
            throw new TransformationFailedException();
        }
    }
}
