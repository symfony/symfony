<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class RangeTest extends TestCase
{
    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "min" or "minPropertyPath" options to be set, not both.');
        new Range([
            'min' => 'min',
            'minPropertyPath' => 'minPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPathNamed()
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "min" or "minPropertyPath" options to be set, not both.');
        new Range(min: 'min', minPropertyPath: 'minPropertyPath');
    }

    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "max" or "maxPropertyPath" options to be set, not both.');
        new Range([
            'max' => 'max',
            'maxPropertyPath' => 'maxPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPathNamed()
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        $this->expectExceptionMessage('requires only one of the "max" or "maxPropertyPath" options to be set, not both.');
        new Range(max: 'max', maxPropertyPath: 'maxPropertyPath');
    }

    public function testThrowsConstraintExceptionIfNoLimitNorPropertyPath()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given');
        new Range([]);
    }

    public function testThrowsNoDefaultOptionConfiguredException()
    {
        $this->expectException(\TypeError::class);
        new Range('value');
    }

    public function testThrowsConstraintDefinitionExceptionIfBothMinAndMaxAndMinMessageOrMaxMessage()
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);
        $this->expectExceptionMessage('can not use "minMessage" and "maxMessage" when the "min" and "max" options are both set. Use "notInRangeMessage" instead.');
        new Range(min: 'min', max: 'max', minMessage: 'minMessage', maxMessage: 'maxMessage');
    }
}
