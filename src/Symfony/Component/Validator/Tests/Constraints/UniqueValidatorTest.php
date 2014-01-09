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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\UniqueValidator;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\ValidationVisitor;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\EntityCollection;

/**
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class UniqueValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ExecutionContext
     *
     * Context mockup
     */
    protected $context;

    /**
     * @var SomeValidator
     *
     * Validator instance
     */
    protected $validator;

    /**
     * Set up method
     */
    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new UniqueValidator();
        $this->validator->initialize($this->context);
    }

    /**
     * Tear down method
     */
    protected function tearDown()
    {
        $this->validator = null;
        $this->context = null;
    }

    /**
     * Tests that if null, just valid
     */
    public function testNullIsValid()
    {
        $this
            ->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(
            null,
            new Unique()
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new Unique());
    }

    /**
     * Test not validate
     */

    public function testUniqueNotSuccess()
    {
        $this
            ->context
            ->expects($this->once())
            ->method('addViolation');

        $this->validator->validate(
            array(
                array(1, 1, 1),
                array(1, 1, 1)
            ),
            new Unique()
        );

    }

    /**
     * Test validate
     */
    public function testUniqueSuccess()
    {
        $this
            ->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(
            array(
                array(1, 1, 1),
                array(2, 2, 2)
            ),
            new Unique()
        );
    }

    /**
     * Functional test, validating satisfactorily Unique constraint
     */
    public function testFunctionalSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collection', new Unique());
        $metadataFactory->addMetadata($metadata);
        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(0, $visitor->getViolations());
    }

    /**
     * Functional test, validating unsatisfactorily Unique constraint
     */
    public function testFunctionalNotSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collectionNotUnique', new Unique());
        $metadataFactory->addMetadata($metadata);
        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(1, $visitor->getViolations());
    }
}