<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Range;

class RangeTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage requires only one of the "min" or "minPropertyPath" options to be set, not both.
     */
    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPath()
    {
        new Range([
            'min' => 'min',
            'minPropertyPath' => 'minPropertyPath',
        ]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage requires only one of the "max" or "maxPropertyPath" options to be set, not both.
     */
    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPath()
    {
        new Range([
            'max' => 'min',
            'maxPropertyPath' => 'maxPropertyPath',
        ]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     * @expectedExceptionMessage Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given
     */
    public function testThrowsConstraintExceptionIfNoLimitNorPropertyPath()
    {
        new Range([]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage No default option is configured
     */
    public function testThrowsNoDefaultOptionConfiguredException()
    {
        new Range('value');
    }
}
