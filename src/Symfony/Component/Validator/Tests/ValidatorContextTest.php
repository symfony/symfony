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
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryAdapter;
use Symfony\Component\Validator\ValidatorContext;

class ValidatorContextTest extends \PHPUnit_Framework_TestCase
{
    protected $context;

    protected function setUp()
    {
        set_error_handler(array($this, "deprecationErrorHandler"));

        $this->context = new ValidatorContext();
    }

    protected function tearDown()
    {
        restore_error_handler();

        $this->context = null;
    }

    public function deprecationErrorHandler($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
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

        $this->assertEquals(new Validator(new ClassMetadataFactoryAdapter($metadataFactory), $validatorFactory, new DefaultTranslator()), $validator);
    }
}
