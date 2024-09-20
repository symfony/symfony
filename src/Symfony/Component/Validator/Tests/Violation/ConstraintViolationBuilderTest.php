<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Violation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConstraintViolationBuilderTest extends TestCase
{
    private array $root;
    private ConstraintViolationList $violations;
    private string $messageTemplate = '%value% is invalid';
    private ConstraintViolationBuilder $builder;

    protected function setUp(): void
    {
        $this->root = [
            'data' => [
                'foo' => 'bar',
                'baz' => 'foobar',
            ],
        ];
        $this->violations = new ConstraintViolationList();
        $this->builder = new ConstraintViolationBuilder($this->violations, new Valid(), $this->messageTemplate, [], $this->root, 'data', 'foo', new IdentityTranslator());
    }

    public function testAddViolation()
    {
        $this->builder->addViolation();

        $this->assertViolationEquals(new ConstraintViolation($this->messageTemplate, $this->messageTemplate, [], $this->root, 'data', 'foo', null, null, new Valid()));
    }

    public function testAppendPropertyPath()
    {
        $this->builder
            ->atPath('foo')
            ->addViolation();

        $this->assertViolationEquals(new ConstraintViolation($this->messageTemplate, $this->messageTemplate, [], $this->root, 'data.foo', 'foo', null, null, new Valid()));
    }

    public function testAppendMultiplePropertyPaths()
    {
        $this->builder
            ->atPath('foo')
            ->atPath('bar')
            ->addViolation();

        $this->assertViolationEquals(new ConstraintViolation($this->messageTemplate, $this->messageTemplate, [], $this->root, 'data.foo.bar', 'foo', null, null, new Valid()));
    }

    public function testCodeCanBeSet()
    {
        $this->builder
            ->setCode('5')
            ->addViolation();

        $this->assertViolationEquals(new ConstraintViolation($this->messageTemplate, $this->messageTemplate, [], $this->root, 'data', 'foo', null, '5', new Valid()));
    }

    public function testCauseCanBeSet()
    {
        $cause = new \LogicException();

        $this->builder
            ->setCause($cause)
            ->addViolation();

        $this->assertViolationEquals(new ConstraintViolation($this->messageTemplate, $this->messageTemplate, [], $this->root, 'data', 'foo', null, null, new Valid(), $cause));
    }

    public function testTranslationDomainFalse()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->willReturn('');

        $builder = new ConstraintViolationBuilder($this->violations, new Valid(), $this->messageTemplate, [], $this->root, 'data', 'foo', $translator);
        $builder->addViolation();

        $builder->disableTranslation();
        $builder->addViolation();
    }

    private function assertViolationEquals(ConstraintViolation $expectedViolation)
    {
        $this->assertCount(1, $this->violations);

        $violation = $this->violations->get(0);

        $this->assertSame($expectedViolation->getMessage(), $violation->getMessage());
        $this->assertSame($expectedViolation->getMessageTemplate(), $violation->getMessageTemplate());
        $this->assertSame($expectedViolation->getParameters(), $violation->getParameters());
        $this->assertSame($expectedViolation->getPlural(), $violation->getPlural());
        $this->assertSame($expectedViolation->getRoot(), $violation->getRoot());
        $this->assertSame($expectedViolation->getPropertyPath(), $violation->getPropertyPath());
        $this->assertSame($expectedViolation->getInvalidValue(), $violation->getInvalidValue());
        $this->assertSame($expectedViolation->getCode(), $violation->getCode());
        $this->assertEquals($expectedViolation->getConstraint(), $violation->getConstraint());
        $this->assertSame($expectedViolation->getCause(), $violation->getCause());
    }
}
