<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Tests\Fixtures\DummyConstraint;
use Symfony\Component\Validator\Tests\Fixtures\DummyConstraintValidator;

class ConstraintValidatorFactoryTest extends TestCase
{
    public function testGetInstance()
    {
        $factory = new ConstraintValidatorFactory();
        $this->assertInstanceOf(DummyConstraintValidator::class, $factory->getInstance(new DummyConstraint()));
    }

    public function testPredefinedGetInstance()
    {
        $validator = new DummyConstraintValidator();
        $factory = new ConstraintValidatorFactory([DummyConstraintValidator::class => $validator]);
        $this->assertSame($validator, $factory->getInstance(new DummyConstraint()));
    }
}
