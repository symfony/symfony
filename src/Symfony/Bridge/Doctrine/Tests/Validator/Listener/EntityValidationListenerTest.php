<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Validator\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Attribute\DisableAutoValidation;
use Symfony\Bridge\Doctrine\Attribute\EnableAutoValidation;
use Symfony\Bridge\Doctrine\Validator\Listener\EntityValidationListener;
use Symfony\Bridge\Doctrine\Validator\Listener\Exception\EntityValidationFailedException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityValidationListenerTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private UnitOfWork&MockObject $uow;
    private ObjectManager&MockObject $manager;

    protected function setUp(): void
    {
        $this->validator = self::createMock(ValidatorInterface::class);
        $this->uow = self::createMock(UnitOfWork::class);
        $this->manager = self::createMock(EntityManagerInterface::class);
        $this->manager->expects(self::once())->method('getUnitOfWork')->willReturn($this->uow);
    }

    protected function tearDown(): void
    {
        unset(
            $this->validator,
            $this->uow,
            $this->manager,
        );
    }

    /**
     * @testWith [true, 2]
     *           [false, 0]
     */
    public function testEntityWithoutAttribute(bool $autoValidate, int $expectedCalls)
    {
        $this->uow->expects(self::once())->method('getScheduledEntityInsertions')->willReturn([
            new TestEntityWithoutAttribute('some-name', 'some@email.com'),
        ]);
        $this->uow->expects(self::once())->method('getScheduledEntityUpdates')->willReturn([
            new TestEntityWithoutAttribute('some-name', 'some@email.com'),
        ]);

        $this->validator->expects(self::exactly($expectedCalls))->method('validate')->willReturn(new ConstraintViolationList());

        $listener = new EntityValidationListener($this->validator, $autoValidate);
        $listener->onFlush(new OnFlushEventArgs($this->manager));
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testEntityWithAttributeEnabled(bool $autoValidate)
    {
        $this->uow->expects(self::once())->method('getScheduledEntityInsertions')->willReturn([
            new TestEntityWithEnableAttribute('some-name', 'some@email.com'),
        ]);
        $this->uow->expects(self::once())->method('getScheduledEntityUpdates')->willReturn([
            new TestEntityWithEnableAttribute('some-name', 'some@email.com'),
        ]);

        $this->validator->expects(self::exactly(2))->method('validate')->willReturn(new ConstraintViolationList());

        $listener = new EntityValidationListener($this->validator, $autoValidate);
        $listener->onFlush(new OnFlushEventArgs($this->manager));
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testEntityWithAttributeDisabled(bool $autoValidate)
    {
        $this->uow->expects(self::once())->method('getScheduledEntityInsertions')->willReturn([
            new TestEntityWithDisableAttribute('some-name', 'some@email.com'),
        ]);
        $this->uow->expects(self::once())->method('getScheduledEntityUpdates')->willReturn([
            new TestEntityWithDisableAttribute('some-name', 'some@email.com'),
        ]);

        $this->validator->expects(self::never())->method('validate');

        $listener = new EntityValidationListener($this->validator, $autoValidate);
        $listener->onFlush(new OnFlushEventArgs($this->manager));
    }

    public function testExceptionIsThrownWhenValidationFails()
    {
        $this->uow->expects(self::once())->method('getScheduledEntityInsertions')->willReturn([
            $object1 = new TestEntityWithEnableAttribute('', 'not-email'),
            $object2 = new TestEntityWithEnableAttribute('some-name', ''),
        ]);
        $this->uow->expects(self::once())->method('getScheduledEntityUpdates')->willReturn([
            $object3 = new TestEntityWithEnableAttribute('foo', 'some@email.com'),
        ]);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
        ;

        $listener = new EntityValidationListener($validator);

        try {
            $listener->onFlush(new OnFlushEventArgs($this->manager));
            self::fail(sprintf('Failed asserting that exception of type "%s" is thrown.', EntityValidationFailedException::class));
        } catch (EntityValidationFailedException $e) {
            $errors = $e->getErrors();

            self::assertCount(3, $errors);

            self::assertInstanceOf(ValidationFailedException::class, $errors[0]);
            self::assertSame($object1, $errors[0]->getValue());
            self::assertCount(3, $violations = $errors[0]->getViolations());
            self::assertInstanceOf(Assert\NotBlank::class, $violations->get(0)->getConstraint());
            self::assertInstanceOf(Assert\Length::class, $violations->get(1)->getConstraint());

            self::assertInstanceOf(ValidationFailedException::class, $errors[1]);
            self::assertSame($object2, $errors[1]->getValue());
            self::assertCount(1, $violations = $errors[1]->getViolations());
            self::assertInstanceOf(Assert\NotBlank::class, $violations->get(0)->getConstraint());

            self::assertInstanceOf(ValidationFailedException::class, $errors[2]);
            self::assertSame($object3, $errors[2]->getValue());
            self::assertCount(1, $violations = $errors[2]->getViolations());
            self::assertInstanceOf(Assert\Length::class, $violations->get(0)->getConstraint());
        }
    }
}

class TestEntityWithoutAttribute
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}

#[EnableAutoValidation]
class TestEntityWithEnableAttribute
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}

#[DisableAutoValidation]
class TestEntityWithDisableAttribute
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}
