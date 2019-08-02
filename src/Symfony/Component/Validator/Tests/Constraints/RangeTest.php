<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Range;

class RangeTest extends TestCase
{
    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('requires only one of the "min" or "minPropertyPath" options to be set, not both.');
        new Range([
            'min' => 'min',
            'minPropertyPath' => 'minPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('requires only one of the "max" or "maxPropertyPath" options to be set, not both.');
        new Range([
            'max' => 'min',
            'maxPropertyPath' => 'maxPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfNoLimitNorPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\MissingOptionsException');
        $this->expectExceptionMessage('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given');
        new Range([]);
    }

    public function testThrowsNoDefaultOptionConfiguredException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('No default option is configured');
        new Range('value');
    }
}
