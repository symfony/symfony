<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Fixtures;

use Symphony\Component\Form\DataTransformerInterface;
use Symphony\Component\Form\Exception\TransformationFailedException;

class FixedDataTransformer implements DataTransformerInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function transform($value)
    {
        if (!array_key_exists($value, $this->mapping)) {
            throw new TransformationFailedException(sprintf('No mapping for value "%s"', $value));
        }

        return $this->mapping[$value];
    }

    public function reverseTransform($value)
    {
        $result = array_search($value, $this->mapping, true);

        if (false === $result) {
            throw new TransformationFailedException(sprintf('No reverse mapping for value "%s"', $value));
        }

        return $result;
    }
}
