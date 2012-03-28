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

use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\ValidatorContext;

class ValidatorContextTest extends \PHPUnit_Framework_TestCase
{
    protected $context;

    protected function setUp()
    {
        $this->context = new ValidatorContext();
    }

    protected function tearDown()
    {
        $this->context = null;
    }

    public function testSetClassMetadataFactory()
    {
        $factory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $result = $this->context->setClassMetadataFactory($factory);

        $this->assertSame($this->context, $result);
        $this->assertSame($factory, $this->context->getClassMetadataFactory());
    }

    public function testSetConstraintValidatorFactory()
    {
        $factory = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');
        $result = $this->context->setConstraintValidatorFactory($factory);

        $this->assertSame($this->context, $result);
        $this->assertSame($factory, $this->context->getConstraintValidatorFactory());
    }

    public function testGetValidator()
    {
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $validatorFactory = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');

        $validator = $this->context
            ->setClassMetadataFactory($metadataFactory)
            ->setConstraintValidatorFactory($validatorFactory)
            ->getValidator();

        $this->assertEquals(new Validator($metadataFactory, $validatorFactory), $validator);
    }
}
