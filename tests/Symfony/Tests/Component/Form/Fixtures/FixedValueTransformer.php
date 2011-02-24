<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;

class FixedValueTransformer implements ValueTransformerInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function transform($value)
    {
        if (!array_key_exists($value, $this->mapping)) {
            throw new \RuntimeException(sprintf('No mapping for value "%s"', $value));
        }

        return $this->mapping[$value];
    }

    public function reverseTransform($value)
    {
        $result = array_search($value, $this->mapping, true);

        if ($result === false) {
            throw new \RuntimeException(sprintf('No reverse mapping for value "%s"', $value));
        }

        return $result;
    }
}