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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\WhenTestWithAttributes;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\WhenTestWithClosure;

final class WhenTest extends TestCase
{
    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testMissingOptionsExceptionIsThrown()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The options "expression", "constraints" must be set for constraint "Symfony\Component\Validator\Constraints\When".');

        new When([]);
    }

    public function testMissingConstraints()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The options "constraints" must be set for constraint "Symfony\Component\Validator\Constraints\When".');

        new When('true');
    }

    public function testNonConstraintsAreRejected()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The value "foo" is not an instance of Constraint in constraint "Symfony\Component\Validator\Constraints\When"');
        new When('true', ['foo']);
    }

    public function testAttributes()
    {
        $loader = new AttributeLoader();
        $metadata = new ClassMetadata(WhenTestWithAttributes::class);

        self::assertTrue($loader->loadClassMetadata($metadata));

        [$classConstraint] = $metadata->getConstraints();

        self::assertInstanceOf(When::class, $classConstraint);
        self::assertSame('true', $classConstraint->expression);
        self::assertEquals([
            new Callback(
                callback: 'callback',
                groups: ['Default', 'WhenTestWithAttributes'],
            ),
        ], $classConstraint->constraints);
        self::assertSame([], $classConstraint->otherwise);

        [$fooConstraint] = $metadata->getPropertyMetadata('foo')[0]->getConstraints();

        self::assertInstanceOf(When::class, $fooConstraint);
        self::assertSame('true', $fooConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['Default', 'WhenTestWithAttributes']),
            new NotBlank(groups: ['Default', 'WhenTestWithAttributes']),
        ], $fooConstraint->constraints);
        self::assertSame([], $fooConstraint->otherwise);
        self::assertSame(['Default', 'WhenTestWithAttributes'], $fooConstraint->groups);

        [$barConstraint] = $metadata->getPropertyMetadata('bar')[0]->getConstraints();

        self::assertInstanceOf(When::class, $barConstraint);
        self::assertSame('false', $barConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['foo']),
            new NotBlank(groups: ['foo']),
        ], $barConstraint->constraints);
        self::assertSame([], $barConstraint->otherwise);
        self::assertSame(['foo'], $barConstraint->groups);

        [$quxConstraint] = $metadata->getPropertyMetadata('qux')[0]->getConstraints();

        self::assertInstanceOf(When::class, $quxConstraint);
        self::assertSame('true', $quxConstraint->expression);
        self::assertEquals([new NotNull(groups: ['foo'])], $quxConstraint->constraints);
        self::assertSame([], $quxConstraint->otherwise);
        self::assertSame(['foo'], $quxConstraint->groups);

        [$bazConstraint] = $metadata->getPropertyMetadata('baz')[0]->getConstraints();

        self::assertInstanceOf(When::class, $bazConstraint);
        self::assertSame('true', $bazConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['Default', 'WhenTestWithAttributes']),
            new NotBlank(groups: ['Default', 'WhenTestWithAttributes']),
        ], $bazConstraint->constraints);
        self::assertSame([], $bazConstraint->otherwise);
        self::assertSame(['Default', 'WhenTestWithAttributes'], $bazConstraint->groups);

        [$quuxConstraint] = $metadata->getPropertyMetadata('quux')[0]->getConstraints();

        self::assertInstanceOf(When::class, $quuxConstraint);
        self::assertSame('true', $quuxConstraint->expression);
        self::assertEquals([new NotNull(groups: ['foo'])], $quuxConstraint->constraints);
        self::assertEquals([new Length(exactly: 10, groups: ['foo'])], $quuxConstraint->otherwise);
        self::assertSame(['foo'], $quuxConstraint->groups);
    }

    #[RequiresPhp('>=8.5')]
    public function testAttributesWithClosure()
    {
        $loader = new AttributeLoader();
        $metadata = new ClassMetadata(WhenTestWithClosure::class);

        self::assertTrue($loader->loadClassMetadata($metadata));

        [$classConstraint] = $metadata->getConstraints();

        self::assertInstanceOf(When::class, $classConstraint);
        self::assertInstanceOf(\Closure::class, $classConstraint->expression);
        self::assertEquals([
            new Callback(
                callback: 'isValid',
                groups: ['Default', 'WhenTestWithClosure'],
            ),
        ], $classConstraint->constraints);
        self::assertSame([], $classConstraint->otherwise);

        [$fooConstraint] = $metadata->getPropertyMetadata('foo')[0]->getConstraints();

        self::assertInstanceOf(When::class, $fooConstraint);
        self::assertInstanceOf(\Closure::class, $fooConstraint->expression);
        self::assertEquals([
            new NotNull(groups: ['Default', 'WhenTestWithClosure']),
            new NotBlank(groups: ['Default', 'WhenTestWithClosure']),
        ], $fooConstraint->constraints);
        self::assertSame([], $fooConstraint->otherwise);
        self::assertSame(['Default', 'WhenTestWithClosure'], $fooConstraint->groups);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testConstraintsInOptionsArray()
    {
        $constraints = [
            new NotNull(),
            new Length(min: 10),
        ];
        $constraint = new When('true', options: ['constraints' => $constraints]);

        $this->assertSame($constraints, $constraint->constraints);
    }
}
