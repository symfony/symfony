<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\RuntimeException;

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
            throw new RuntimeException(sprintf('No mapping for value "%s"', $value));
        }

        return $this->mapping[$value];
    }

    public function reverseTransform($value)
    {
        $result = array_search($value, $this->mapping, true);

        if ($result === false) {
            throw new RuntimeException(sprintf('No reverse mapping for value "%s"', $value));
        }

        return $result;
    }
}
