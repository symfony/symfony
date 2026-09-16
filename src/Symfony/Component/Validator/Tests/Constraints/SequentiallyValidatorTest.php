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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\GroupSequenceProvider;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\SequentiallyValidator;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\GroupSequenceProviderInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

class SequentiallyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SequentiallyValidator
    {
        return new SequentiallyValidator();
    }

    public function testWalkThroughConstraints()
    {
        $constraints = [
            new Type('number'),
            new Range(min: 4),
        ];

        $value = 6;

        $this->expectValidateValue(0, $value, [$constraints[0]]);
        $this->expectValidateValue(1, $value, [$constraints[1]]);

        $this->validate($value, new Sequentially($constraints));

        $this->assertNoViolation();
    }

    public function testStopsAtFirstConstraintWithViolations()
    {
        $constraints = [
            new Type('string'),
            new Regex(pattern: '[a-z]'),
            new NotEqualTo('Foo'),
        ];

        $value = 'Foo';

        $this->expectValidateValue(0, $value, [$constraints[0]]);
        $this->expectFailingValueValidation(1, $value, [$constraints[1]], null, new ConstraintViolation('regex error', null, [], null, '', null, null, 'regex'));

        $this->validate($value, new Sequentially($constraints));

        $this->assertCount(1, $this->context->getViolations());
    }

    public function testNestedConstraintsAreNotExecutedWhenGroupDoesNotMatch()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(50, new Sequentially(
            constraints: [
                new GreaterThan(
                    groups: ['senior'],
                    value: 55,
                ),
                new Range(
                    groups: ['adult'],
                    min: 18,
                    max: 55,
                ),
            ],
            groups: ['adult', 'senior'],
        ), 'adult');

        $this->assertCount(0, $violations);
    }

    public function testValidCascadesAfterPreviousConstraintsPass()
    {
        $container = new SequentiallyValidContainer();
        $container->child = new SequentiallyValidChild();

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('child.name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
        $this->assertSame($container, $violations[0]->getRoot());
    }

    public function testValidDoesNotCascadeWhenTypeConstraintFails()
    {
        $container = new SequentiallyValidContainer();
        $container->child = 'not an object';

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('child', $violations[0]->getPropertyPath());
        $this->assertSame(Type::INVALID_TYPE_ERROR, $violations[0]->getCode());
        $this->assertSame('not an object', $violations[0]->getInvalidValue());
    }

    public function testValidDoesNotCascadeObjectOfWrongType()
    {
        $container = new SequentiallyValidContainer();
        $container->child = new SequentiallyInvalidChild();

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('child', $violations[0]->getPropertyPath());
        $this->assertSame(Type::INVALID_TYPE_ERROR, $violations[0]->getCode());
    }

    public function testValidIgnoresNullAfterTypeConstraintPasses()
    {
        $container = new SequentiallyValidContainer();

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(0, $violations);
    }

    public function testValidCascadesCurrentSequentiallyGroup()
    {
        $container = new SequentiallyValidContainer();
        $container->groupedChild = new SequentiallyValidChild();
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $this->assertCount(0, $validator->validate($container));

        $violations = $validator->validate($container, groups: ['custom']);

        $this->assertCount(1, $violations);
        $this->assertSame('groupedChild.name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    public function testValidCascadesCurrentGroupFromGroupSequence()
    {
        $container = new SequentiallyValidContainer();
        $container->groupSequenceChild = new SequentiallyValidChild('value');

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container, groups: new GroupSequence(['first', 'second']));

        $this->assertCount(1, $violations);
        $this->assertSame('groupSequenceChild.secondName', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    #[DataProvider('provideClassGroupSequenceContainers')]
    public function testValidPreservesClassGroupSequenceCascading(object $nestedContainer, object $directContainer, array $expectedMessages)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $nestedViolations = $validator->validate($nestedContainer);
        $directViolations = $validator->validate($directContainer);

        $this->assertSame($expectedMessages, array_map(static fn ($violation) => $violation->getMessage(), iterator_to_array($nestedViolations)));
        $this->assertSame($expectedMessages, array_map(static fn ($violation) => $violation->getMessage(), iterator_to_array($directViolations)));
        $this->assertSame(
            array_map(static fn ($violation) => $violation->getPropertyPath(), iterator_to_array($directViolations)),
            array_map(static fn ($violation) => $violation->getPropertyPath(), iterator_to_array($nestedViolations)),
        );
    }

    public static function provideClassGroupSequenceContainers(): iterable
    {
        $nested = new SequentiallyStaticGroupSequenceContainer();
        $nested->child = new SequentiallyGroupSequenceChild();
        $direct = new DirectStaticGroupSequenceContainer();
        $direct->child = new SequentiallyGroupSequenceChild();

        yield 'static sequence' => [$nested, $direct, ['default violation']];

        $nested = new SequentiallyCascadingStaticGroupSequenceContainer();
        $nested->child = new SequentiallyGroupSequenceChild();
        $direct = new DirectCascadingStaticGroupSequenceContainer();
        $direct->child = new SequentiallyGroupSequenceChild();

        yield 'static sequence cascading the current group' => [$nested, $direct, ['default violation', 'current group violation']];

        $nested = new SequentiallyGroupSequenceProviderContainer();
        $nested->child = new SequentiallyGroupSequenceChild();
        $direct = new DirectGroupSequenceProviderContainer();
        $direct->child = new SequentiallyGroupSequenceChild();

        yield 'sequence provider' => [$nested, $direct, ['default violation']];

        $nested = new SequentiallyCascadingGroupSequenceProviderContainer();
        $nested->child = new SequentiallyGroupSequenceChild();
        $direct = new DirectCascadingGroupSequenceProviderContainer();
        $direct->child = new SequentiallyGroupSequenceChild();

        yield 'sequence provider cascading the current group' => [$nested, $direct, ['default violation', 'current group violation']];
    }

    #[DataProvider('provideComposedClassGroupSequenceContainers')]
    public function testValidPreservesClassGroupSequenceCascadingThroughComposites(object $container, array $expectedViolations)
    {
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);
        $actualViolations = array_map(
            static fn ($violation) => [$violation->getPropertyPath(), $violation->getMessage()],
            iterator_to_array($violations),
        );

        $this->assertSame($expectedViolations, $actualViolations);
    }

    public static function provideComposedClassGroupSequenceContainers(): iterable
    {
        $defaultViolations = [
            ['allChildren[0].defaultName', 'default violation'],
            ['nestedChild.defaultName', 'default violation'],
            ['compoundChild.defaultName', 'default violation'],
            ['collectionChildren[child].defaultName', 'default violation'],
            ['whenChild.defaultName', 'default violation'],
            ['atLeastOneOfChild', 'This value should satisfy at least one of the following constraints:'],
        ];
        $cascadingViolations = [
            ['allChildren[0].defaultName', 'default violation'],
            ['allChildren[0].currentGroupName', 'current group violation'],
            ['nestedChild.defaultName', 'default violation'],
            ['nestedChild.currentGroupName', 'current group violation'],
            ['compoundChild.defaultName', 'default violation'],
            ['compoundChild.currentGroupName', 'current group violation'],
            ['collectionChildren[child].defaultName', 'default violation'],
            ['collectionChildren[child].currentGroupName', 'current group violation'],
            ['whenChild.defaultName', 'default violation'],
            ['whenChild.currentGroupName', 'current group violation'],
            ['atLeastOneOfChild', 'This value should satisfy at least one of the following constraints:'],
            ['atLeastOneOfCurrentGroupChild', 'This value should satisfy at least one of the following constraints:'],
        ];

        yield 'static sequence' => [self::createComposedContainer(new ComposedStaticGroupSequenceContainer()), $defaultViolations];
        yield 'static sequence cascading the current group' => [self::createComposedContainer(new ComposedCascadingStaticGroupSequenceContainer()), $cascadingViolations];
        yield 'sequence provider' => [self::createComposedContainer(new ComposedGroupSequenceProviderContainer()), $defaultViolations];
        yield 'sequence provider cascading the current group' => [self::createComposedContainer(new ComposedCascadingGroupSequenceProviderContainer()), $cascadingViolations];
    }

    private static function createComposedContainer(object $container): object
    {
        $container->allChildren = [new SequentiallyGroupSequenceChild()];
        $container->nestedChild = new SequentiallyGroupSequenceChild();
        $container->compoundChild = new SequentiallyGroupSequenceChild();
        $container->collectionChildren = ['child' => new SequentiallyGroupSequenceChild()];
        $container->whenChild = new SequentiallyGroupSequenceChild();
        $container->atLeastOneOfChild = new SequentiallyValidChild();
        $container->atLeastOneOfCurrentGroupChild = new SequentiallyCurrentGroupOnlyChild();

        return $container;
    }

    public function testAllRunsValidSequenceForEachElement()
    {
        $container = new SequentiallyValidContainer();
        $container->children = [
            'scalar' => 'not an object',
            'object' => new SequentiallyValidChild(),
            'valid' => new SequentiallyValidChild('value'),
        ];

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(2, $violations);
        $this->assertSame('children[scalar]', $violations[0]->getPropertyPath());
        $this->assertSame(Type::INVALID_TYPE_ERROR, $violations[0]->getCode());
        $this->assertSame('children[object].name', $violations[1]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[1]->getCode());
    }

    public function testValidCascadesObjectsInArrayAfterArrayTypePasses()
    {
        $container = new SequentiallyValidContainer();
        $container->arrayChildren = [
            'scalar' => 'ignored by cascading',
            'object' => new SequentiallyValidChild(),
        ];

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('arrayChildren[object].name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    public function testValidHonorsDisabledTraversalFromSequentially()
    {
        $container = new SequentiallyValidContainer();
        $container->nonTraversedChildren = new \ArrayIterator([new SequentiallyValidChild()]);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(0, $violations);
    }

    public function testValidTraversesTraversableFromSequentially()
    {
        $container = new SequentiallyValidContainer();
        $container->traversedChildren = new \ArrayIterator([new SequentiallyValidChild()]);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('traversedChildren[0].name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    public function testValidNestedInCompoundHonorsDisabledTraversal()
    {
        $value = new \ArrayIterator([new SequentiallyValidChild()]);
        $constraint = new CompoundWithSequentiallyValid(groups: ['custom']);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($value, $constraint, ['custom']);

        $this->assertCount(0, $violations);
        $this->assertNull($constraint->constraints[0]->constraints[1]->groups);
    }

    public function testValidViolationStopsLaterConstraints()
    {
        $container = new SequentiallyValidContainer();
        $container->childValidatedBeforeLaterConstraint = new SequentiallyValidChild();

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(1, $violations);
        $this->assertSame('childValidatedBeforeLaterConstraint.name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    public function testValidWithoutGuardKeepsScalarFailureBehavior()
    {
        $container = new SequentiallyValidContainer();
        $container->unguardedChild = 'not an object';

        $this->expectException(NoSuchMetadataException::class);
        $this->expectExceptionMessage('Cannot create metadata for non-objects. Got: "string".');

        Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);
    }

    public function testSerializedSequentiallyValidConstraintCascadesAtRuntime()
    {
        $constraint = unserialize(serialize(new Sequentially([
            new Type(SequentiallyValidChild::class),
            new Valid(),
        ])));

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate(new SequentiallyValidChild(), $constraint);

        $this->assertCount(1, $violations);
        $this->assertSame('name', $violations[0]->getPropertyPath());
        $this->assertSame(NotBlank::IS_BLANK_ERROR, $violations[0]->getCode());
    }

    public function testContextualValidOutsideSequentiallyKeepsCurrentGroup()
    {
        $container = new ContextualValidGroupSequenceContainer();
        $container->child = new ContextualValidGroupSequenceChild();

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($container);

        $this->assertCount(4, $violations);
        $this->assertSame('child.implicit.currentGroupName', $violations[0]->getPropertyPath());
        $this->assertSame('current group violation', $violations[0]->getMessage());
        $this->assertSame('child.explicit.currentGroupName', $violations[1]->getPropertyPath());
        $this->assertSame('current group violation', $violations[1]->getMessage());
        $this->assertSame('child.mixed.defaultName', $violations[2]->getPropertyPath());
        $this->assertSame('default violation', $violations[2]->getMessage());
        $this->assertSame('child.mixed.currentGroupName', $violations[3]->getPropertyPath());
        $this->assertSame('current group violation', $violations[3]->getMessage());
    }
}

class SequentiallyValidContainer
{
    #[Sequentially([
        new Type(SequentiallyValidChild::class),
        new Valid(),
    ])]
    public mixed $child = null;

    #[Sequentially(
        constraints: [
            new Type(SequentiallyValidChild::class),
            new Valid(),
        ],
        groups: ['custom'],
    )]
    public mixed $groupedChild = null;

    #[Sequentially(
        constraints: [
            new Type(SequentiallyValidChild::class),
            new Valid(),
        ],
        groups: ['first', 'second'],
    )]
    public mixed $groupSequenceChild = null;

    #[All([
        new Sequentially([
            new Type(SequentiallyValidChild::class),
            new Valid(),
        ]),
    ])]
    public mixed $children = null;

    #[Sequentially([
        new Type('array'),
        new Valid(),
    ])]
    public mixed $arrayChildren = null;

    #[Sequentially([
        new Type(\Traversable::class),
        new Valid(traverse: false),
    ])]
    public mixed $nonTraversedChildren = null;

    #[Sequentially([
        new Type(\Traversable::class),
        new Valid(),
    ])]
    public mixed $traversedChildren = null;

    #[Sequentially([
        new Valid(),
        new Type('string'),
    ])]
    public mixed $childValidatedBeforeLaterConstraint = null;

    #[Sequentially([new Valid()])]
    public mixed $unguardedChild = null;
}

class SequentiallyValidChild
{
    #[NotBlank(groups: ['Default', 'custom'])]
    public mixed $name;

    #[NotBlank(groups: ['second'])]
    public mixed $secondName = null;

    public function __construct(mixed $name = null)
    {
        $this->name = $name;
    }
}

class SequentiallyInvalidChild
{
    #[NotBlank]
    public mixed $name = null;
}

#[GroupSequence(['first', 'SequentiallyStaticGroupSequenceContainer'])]
class SequentiallyStaticGroupSequenceContainer
{
    #[Sequentially([new Valid()])]
    public mixed $child = null;
}

#[GroupSequence(['first', 'DirectStaticGroupSequenceContainer'])]
class DirectStaticGroupSequenceContainer
{
    #[Valid(groups: ['DirectStaticGroupSequenceContainer'], restrictGroups: false)]
    public mixed $child = null;
}

#[GroupSequence(['first', 'SequentiallyCascadingStaticGroupSequenceContainer'], cascadeCurrentGroup: true)]
class SequentiallyCascadingStaticGroupSequenceContainer
{
    #[Sequentially([new Valid()])]
    public mixed $child = null;
}

#[GroupSequence(['first', 'DirectCascadingStaticGroupSequenceContainer'], cascadeCurrentGroup: true)]
class DirectCascadingStaticGroupSequenceContainer
{
    #[Valid(groups: ['DirectCascadingStaticGroupSequenceContainer'], restrictGroups: false)]
    public mixed $child = null;
}

#[GroupSequenceProvider]
class SequentiallyGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    #[Sequentially([new Valid()])]
    public mixed $child = null;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'SequentiallyGroupSequenceProviderContainer'];
    }
}

#[GroupSequenceProvider]
class DirectGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    #[Valid(groups: ['DirectGroupSequenceProviderContainer'], restrictGroups: false)]
    public mixed $child = null;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'DirectGroupSequenceProviderContainer'];
    }
}

#[GroupSequenceProvider(cascadeCurrentGroup: true)]
class SequentiallyCascadingGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    #[Sequentially([new Valid()])]
    public mixed $child = null;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'SequentiallyCascadingGroupSequenceProviderContainer'];
    }
}

#[GroupSequenceProvider(cascadeCurrentGroup: true)]
class DirectCascadingGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    #[Valid(groups: ['DirectCascadingGroupSequenceProviderContainer'], restrictGroups: false)]
    public mixed $child = null;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'DirectCascadingGroupSequenceProviderContainer'];
    }
}

class SequentiallyGroupSequenceChild
{
    #[NotBlank(message: 'default violation')]
    public mixed $defaultName = null;

    #[NotBlank(
        message: 'current group violation',
        groups: [
            'SequentiallyCascadingStaticGroupSequenceContainer',
            'DirectCascadingStaticGroupSequenceContainer',
            'SequentiallyCascadingGroupSequenceProviderContainer',
            'DirectCascadingGroupSequenceProviderContainer',
            'ComposedCascadingStaticGroupSequenceContainer',
            'ComposedCascadingGroupSequenceProviderContainer',
        ],
    )]
    public mixed $currentGroupName = null;
}

class SequentiallyCurrentGroupOnlyChild
{
    #[NotBlank(
        groups: [
            'ComposedCascadingStaticGroupSequenceContainer',
            'ComposedCascadingGroupSequenceProviderContainer',
        ],
    )]
    public mixed $name = null;
}

class CompoundWithSequentiallyValid extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Sequentially([
                new Type(\Traversable::class),
                new Valid(traverse: false),
            ]),
        ];
    }
}

trait ComposedGroupSequenceProperties
{
    #[All([new Sequentially([new Valid()])])]
    public array $allChildren = [];

    #[Sequentially([new Sequentially([new Valid()])])]
    public mixed $nestedChild = null;

    #[CompoundWithValidSequence]
    public mixed $compoundChild = null;

    #[Collection(fields: ['child' => new Required([new Sequentially([new Valid()])])])]
    public array $collectionChildren = [];

    #[When('true', [new Sequentially([new Valid()])])]
    public mixed $whenChild = null;

    #[AtLeastOneOf([new Sequentially([new Valid()])], includeInternalMessages: false)]
    public mixed $atLeastOneOfChild = null;

    #[AtLeastOneOf([new Sequentially([new Valid()])], includeInternalMessages: false)]
    public mixed $atLeastOneOfCurrentGroupChild = null;
}

#[GroupSequence(['first', 'ComposedStaticGroupSequenceContainer'])]
class ComposedStaticGroupSequenceContainer
{
    use ComposedGroupSequenceProperties;
}

#[GroupSequence(['first', 'ComposedCascadingStaticGroupSequenceContainer'], cascadeCurrentGroup: true)]
class ComposedCascadingStaticGroupSequenceContainer
{
    use ComposedGroupSequenceProperties;
}

#[GroupSequenceProvider]
class ComposedGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    use ComposedGroupSequenceProperties;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'ComposedGroupSequenceProviderContainer'];
    }
}

#[GroupSequenceProvider(cascadeCurrentGroup: true)]
class ComposedCascadingGroupSequenceProviderContainer implements GroupSequenceProviderInterface
{
    use ComposedGroupSequenceProperties;

    public function getGroupSequence(): array|GroupSequence
    {
        return ['first', 'ComposedCascadingGroupSequenceProviderContainer'];
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CompoundWithValidSequence extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [new Sequentially([new Valid()])];
    }
}

#[GroupSequence(['first', 'ContextualValidGroupSequenceContainer'])]
class ContextualValidGroupSequenceContainer
{
    #[ContextualValid]
    public mixed $child = null;
}

class ContextualValidGroupSequenceChild
{
    #[NotBlank(message: 'default violation')]
    public mixed $defaultName = null;

    #[NotBlank(message: 'current group violation', groups: ['ContextualValidGroupSequenceContainer'])]
    public mixed $currentGroupName = null;
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ContextualValid extends Constraint
{
    public function validatedBy(): string
    {
        return ContextualValidValidator::class;
    }
}

class ContextualValidValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContextualValid) {
            throw new UnexpectedTypeException($constraint, ContextualValid::class);
        }

        $this->context->getValidator()->inContext($this->context)->atPath('implicit')->validate($value);
        $this->context->getValidator()->inContext($this->context)->atPath('explicit')->validate(\is_object($value) ? clone $value : $value, new Valid());
        $this->context->getValidator()->inContext($this->context)->atPath('mixed')->validate(\is_object($value) ? clone $value : $value, [
            new Valid(),
            new Sequentially([new Valid()], groups: [$this->context->getGroup()]),
        ]);
    }
}
