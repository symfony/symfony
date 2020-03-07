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
use Symfony\Component\Validator\Constraints\NotInRange;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class NotInRangeTest extends TestCase
{
    public function testThrowsConstraintExceptionIfBothMinLimitAndPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('requires only one of the "min" or "minPropertyPath" options to be set, not both.');
        new NotInRange([
            'min' => 'min',
            'minPropertyPath' => 'minPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfBothMaxLimitAndPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('requires only one of the "max" or "maxPropertyPath" options to be set, not both.');
        new NotInRange([
            'max' => 'max',
            'maxPropertyPath' => 'maxPropertyPath',
        ]);
    }

    public function testThrowsConstraintExceptionIfNoMinNorMinPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\MissingOptionsException');
        $this->expectExceptionMessage('Either option "min" or "minPropertyPath" must be given');
        new NotInRange([
            'max' => 'max',
        ]);
    }

    public function testThrowsConstraintExceptionIfNoMaxNorMaxPropertyPath()
    {
        $this->expectException('Symfony\Component\Validator\Exception\MissingOptionsException');
        $this->expectExceptionMessage('Either option "max" or "maxPropertyPath" must be given');
        new NotInRange([
            'min' => 'min',
        ]);
    }

    public function testThrowsNoDefaultOptionConfiguredException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        $this->expectExceptionMessage('No default option is configured');
        new NotInRange('value');
    }
}
