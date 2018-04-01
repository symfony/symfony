<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Validator;

use Symphony\Component\Translation\IdentityTranslator;
use Symphony\Component\Validator\ConstraintValidatorFactory;
use Symphony\Component\Validator\Context\ExecutionContextFactory;
use Symphony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symphony\Component\Validator\Tests\Constraints\Fixtures\ChildA;
use Symphony\Component\Validator\Tests\Constraints\Fixtures\ChildB;
use Symphony\Component\Validator\Tests\Fixtures\Entity;
use Symphony\Component\Validator\Validator\RecursiveValidator;

class RecursiveValidatorTest extends AbstractTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array())
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('en');

        $contextFactory = new ExecutionContextFactory($translator);
        $validatorFactory = new ConstraintValidatorFactory();

        return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $objectInitializers);
    }

    public function testEmptyGroupsArrayDoesNotTriggerDeprecation()
    {
        $entity = new Entity();
        $childA = new ChildA();
        $childB = new ChildB();
        $childA->name = false;
        $childB->name = 'fake';
        $entity->childA = array($childA);
        $entity->childB = array($childB);
        $validatorContext = $this->getMockBuilder('Symphony\Component\Validator\Validator\ContextualValidatorInterface')->getMock();
        $validatorContext
            ->expects($this->once())
            ->method('validate')
            ->with($entity, null, array())
            ->willReturnSelf();

        $validator = $this
            ->getMockBuilder('Symphony\Component\Validator\Validator\RecursiveValidator')
            ->disableOriginalConstructor()
            ->setMethods(array('startContext'))
            ->getMock();
        $validator
            ->expects($this->once())
            ->method('startContext')
            ->willReturn($validatorContext);

        $validator->validate($entity, null, array());
    }

    public function testRelationBetweenChildAAndChildB()
    {
        $entity = new Entity();
        $childA = new ChildA();
        $childB = new ChildB();

        $childA->childB = $childB;
        $childB->childA = $childA;

        $childA->name = false;
        $childB->name = 'fake';
        $entity->childA = array($childA);
        $entity->childB = array($childB);

        $validatorContext = $this->getMockBuilder('Symphony\Component\Validator\Validator\ContextualValidatorInterface')->getMock();
        $validatorContext
            ->expects($this->once())
            ->method('validate')
            ->with($entity, null, array())
            ->willReturnSelf();

        $validator = $this
            ->getMockBuilder('Symphony\Component\Validator\Validator\RecursiveValidator')
            ->disableOriginalConstructor()
            ->setMethods(array('startContext'))
            ->getMock();
        $validator
            ->expects($this->once())
            ->method('startContext')
            ->willReturn($validatorContext);

        $validator->validate($entity, null, array());
    }
}
