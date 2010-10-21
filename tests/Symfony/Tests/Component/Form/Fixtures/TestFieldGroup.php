<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\FieldGroup;
use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;

class TestFieldGroup extends FieldGroup
{
    /**
     * Expose method for testing purposes
     */
    public function setNormalizationTransformer(ValueTransformerInterface $normalizationTransformer)
    {
        parent::setNormalizationTransformer($normalizationTransformer);
    }

    /**
     * Expose method for testing purposes
     */
    public function setValueTransformer(ValueTransformerInterface $valueTransformer)
    {
        parent::setValueTransformer($valueTransformer);
    }

    /**
     * Expose method for testing purposes
     */
    public function getNormalizedData()
    {
        return parent::getNormalizedData();
    }
}