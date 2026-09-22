<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DependencyInjection\AttributeReader;
use Symfony\Component\Workflow\DependencyInjection\TransitionDescriptor;
use Symfony\Component\Workflow\DependencyInjection\WorkflowDescriptor;
use Symfony\Component\Workflow\Tests\Fixtures\DefinitionValidator;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowType;

class AttributeReaderTest extends TestCase
{
    public function testMinimalDefinition()
    {
        $workflow = $this->read(TaskWorkflow::class);

        $this->assertSame('task', $workflow->name);
        $this->assertSame(WorkflowType::StateMachine, $workflow->type);
        $this->assertSame(['new' => [], 'processing' => [], 'done' => []], $workflow->places);
        $this->assertSame([], $workflow->initialMarking);
        $this->assertSame([\stdClass::class], $workflow->supports);
        $this->assertNull($workflow->supportStrategy);
        $this->assertNull($workflow->markingProperty);
        $this->assertNull($workflow->markingStore);
        $this->assertSame([], $workflow->metadata);
        $this->assertFalse($workflow->auditTrail);
        $this->assertNull($workflow->eventsToDispatch);
        $this->assertSame([], $workflow->definitionValidators);

        $this->assertCount(2, $workflow->transitions);
        $this->assertTransition($workflow->transitions[0], 'start', [['new', 1]], [['processing', 1]]);
        $this->assertTransition($workflow->transitions[1], 'finish', [['processing', 1]], [['done', 1]]);
    }

    #[DataProvider('provideNames')]
    public function testNameIsDerivedFromTheClassName(string $class, string $expectedName)
    {
        $this->assertSame($expectedName, $this->read($class)->name);
    }

    public static function provideNames(): iterable
    {
        yield [TaskWorkflow::class, 'task'];
        yield [PullRequestStateMachine::class, 'pull_request'];
        yield [OrderV2Workflow::class, 'order_v2'];
        yield [HTMLPageWorkflow::class, 'html_page'];
        yield [Reviewing::class, 'reviewing'];
        yield [NamedWorkflow::class, 'explicit.name'];
    }

    public function testFullDefinition()
    {
        $workflow = $this->read(FullWorkflow::class);

        $this->assertSame('full', $workflow->name);
        $this->assertSame(WorkflowType::Workflow, $workflow->type);
        $this->assertSame([\stdClass::class, \Countable::class], $workflow->supports);
        $this->assertNull($workflow->supportStrategy);
        $this->assertSame('step', $workflow->markingProperty);
        $this->assertNull($workflow->markingStore);
        $this->assertSame(['title' => 'Full'], $workflow->metadata);
        $this->assertTrue($workflow->auditTrail);
        $this->assertSame(['!workflow.announce'], $workflow->eventsToDispatch);
        $this->assertSame([DefinitionValidator::class], $workflow->definitionValidators);
        $this->assertSame(['new', 'extra'], $workflow->initialMarking);

        // Explicit places first, then the places inferred from the transitions, in order
        $this->assertSame([
            'extra' => ['label' => 'Extra'],
            'archived' => [],
            'orphan' => [],
            'done' => [],
            'new' => ['label' => 'New'],
            'processing' => ['label' => 'Processing', 'bg_color' => '#eee'],
            'failed' => [],
        ], $workflow->places);

        // Transitions of the attribute first, then the ones of the constants, in order
        $this->assertCount(4, $workflow->transitions);
        $this->assertTransition($workflow->transitions[0], 'merge', [['done', 2], ['extra', 1]], [['archived', 1]], 'true', ['label' => 'Merge']);
        $this->assertTransition($workflow->transitions[1], 'start', [['new', 1]], [['processing', 1]], 'is_granted("ROLE_USER")', ['color' => 'blue']);
        $this->assertTransition($workflow->transitions[2], 'finish', [['processing', 1]], [['done', 1]]);
        $this->assertTransition($workflow->transitions[3], 'finish', [['failed', 1]], [['done', 1]]);
    }

    public function testSupportStrategyAndMarkingStore()
    {
        $workflow = $this->read(ServicesWorkflow::class);

        $this->assertSame([], $workflow->supports);
        $this->assertSame('app.support_strategy', $workflow->supportStrategy);
        $this->assertNull($workflow->markingProperty);
        $this->assertSame('app.marking_store', $workflow->markingStore);
    }

    public function testPlacesFromAnEnum()
    {
        $workflow = $this->read(EnumPlacesWorkflow::class);

        $this->assertSame([
            'new' => ['label' => 'New'],
            'processing' => ['label' => 'Processing', 'bg_color' => '#eee'],
            'done' => [],
            'failed' => [],
            'archived' => [],
        ], $workflow->places);
        $this->assertCount(1, $workflow->transitions);
        $this->assertTransition($workflow->transitions[0], 'archive', [['done', 1]], [['archived', 1]]);
    }

    #[DataProvider('provideInvalidDefinitions')]
    public function testInvalidDefinition(string $class, string $expectedMessage)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->read($class);
    }

    public static function provideInvalidDefinitions(): iterable
    {
        $ns = __NAMESPACE__.'\\';

        yield 'name on a constant' => [NameOnConstantWorkflow::class, 'The "name" argument of "#[Symfony\Component\Workflow\Attribute\Transition]" cannot be used on "'.$ns.'NameOnConstantWorkflow::GO": the value of the constant is the name of the transition.'];
        yield 'non-string constant' => [IntConstantWorkflow::class, 'The value of "'.$ns.'IntConstantWorkflow::GO" must be a non-empty string to be used as the name of a transition, "int" given.'];
        yield 'empty guard' => [EmptyGuardWorkflow::class, 'The "guard" argument of the "go" transition of the workflow defined by "'.$ns.'EmptyGuardWorkflow" cannot be empty.'];
        yield 'empty from' => [EmptyFromWorkflow::class, 'The "from" argument of the "go" transition of the workflow defined by "'.$ns.'EmptyFromWorkflow" cannot be empty.'];
        yield 'invalid arc' => [InvalidArcWorkflow::class, 'The "to" argument of the "go" transition of the workflow defined by "'.$ns.'InvalidArcWorkflow" must be a list of "Symfony\Component\Workflow\Arc" instances, enum cases or strings, "int" given.'];
        yield 'int-backed enum' => [IntEnumWorkflow::class, 'Only string-backed enums can be used as places of the workflow defined by "'.$ns.'IntEnumWorkflow", "'.$ns.'IntStep" is not.'];
        yield 'supports and support strategy' => [SupportsAndStrategyWorkflow::class, 'The "supports" and "supportStrategy" arguments of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" cannot be used together on "'.$ns.'SupportsAndStrategyWorkflow".'];
        yield 'marking property and marking store' => [MarkingPropertyAndStoreWorkflow::class, 'The "markingProperty" and "markingStore" arguments of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" cannot be used together on "'.$ns.'MarkingPropertyAndStoreWorkflow".'];
        yield 'missing supported class' => [MissingSupportWorkflow::class, 'The supported class or interface "Missing\Subject" of the workflow defined by "'.$ns.'MissingSupportWorkflow" does not exist.'];
        yield 'unknown event' => [UnknownEventWorkflow::class, 'The "eventsToDispatch" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'UnknownEventWorkflow" must be a list of workflow events (like "workflow.enter"), "workflow.unknown" given.'];
        yield 'non-string event' => [NonStringEventWorkflow::class, 'The "eventsToDispatch" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'NonStringEventWorkflow" must be a list of event names, "int" given.'];
        yield 'mixed events' => [MixedEventsWorkflow::class, 'Cannot mix allow-list and block-list entries in the "eventsToDispatch" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'MixedEventsWorkflow": every entry must start with "!" (block-list mode) or none of them must (allow-list mode).'];
        yield 'disabled guard event' => [DisabledGuardEventWorkflow::class, 'The "workflow.guard" event cannot be disabled in the "eventsToDispatch" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'DisabledGuardEventWorkflow": it is always dispatched.'];
        yield 'missing validator' => [MissingValidatorWorkflow::class, 'The definition validator "Missing\Validator" of the workflow defined by "'.$ns.'MissingValidatorWorkflow" does not exist.'];
        yield 'wrong validator' => [WrongValidatorWorkflow::class, 'The definition validator "DateTime" of the workflow defined by "'.$ns.'WrongValidatorWorkflow" must implement "Symfony\Component\Workflow\Validator\DefinitionValidatorInterface".'];
        yield 'validator with a required argument' => [ValidatorWithArgumentWorkflow::class, 'The constructor of the definition validator "'.$ns.'ValidatorWithRequiredArgument" of the workflow defined by "'.$ns.'ValidatorWithArgumentWorkflow" must not have any required argument.'];
        yield 'empty name' => [EmptyNameWorkflow::class, 'The name of the workflow defined by "'.$ns.'EmptyNameWorkflow" cannot be empty.'];
        yield 'no transition' => [NoTransitionWorkflow::class, 'The workflow defined by "'.$ns.'NoTransitionWorkflow" must define at least one transition.'];
        yield 'duplicate place' => [DuplicatePlaceWorkflow::class, 'The place "a" is defined twice in the "places" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'DuplicatePlaceWorkflow".'];
        yield 'place without name' => [PlaceWithoutNameWorkflow::class, 'The "name" argument of "Symfony\Component\Workflow\Attribute\Place" is required when it is used in the "places" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'PlaceWithoutNameWorkflow".'];
        yield 'invalid place' => [InvalidPlaceWorkflow::class, 'The "places" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'InvalidPlaceWorkflow" must be a list of "Symfony\Component\Workflow\Attribute\Place" instances, enum cases or strings, "int" given.'];
        yield 'places from a class' => [PlacesFromClassWorkflow::class, 'The "places" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'PlacesFromClassWorkflow" must be a list of places or the name of a string-backed enum, "stdClass" given.'];
        yield 'place not in the enum' => [PlaceNotInEnumWorkflow::class, 'The place "foreign" of the workflow defined by "'.$ns.'PlaceNotInEnumWorkflow" is not a case of "'.$ns.'TaskStep".'];
        yield 'name on an enum case' => [NameOnCaseWorkflow::class, 'The "name" argument of "#[Symfony\Component\Workflow\Attribute\Place]" cannot be used on "'.$ns.'NamedStep::A": the value of the case is the name of the place.'];
        yield 'transition without name' => [TransitionWithoutNameWorkflow::class, 'The "name" argument of "Symfony\Component\Workflow\Attribute\Transition" is required when it is used in the "transitions" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'TransitionWithoutNameWorkflow".'];
        yield 'invalid transition' => [InvalidTransitionWorkflow::class, 'The "transitions" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'InvalidTransitionWorkflow" must be a list of "Symfony\Component\Workflow\Attribute\Transition" instances, "string" given.'];
        yield 'unknown initial place' => [UnknownInitialPlaceWorkflow::class, 'The initial place "nope" of the workflow defined by "'.$ns.'UnknownInitialPlaceWorkflow" is not a place of the workflow.'];
        yield 'invalid initial place' => [InvalidInitialPlaceWorkflow::class, 'The "initialMarking" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'InvalidInitialPlaceWorkflow" must be a list of enum cases or strings, "int" given.'];
    }

    private function read(string $class): WorkflowDescriptor
    {
        $reflection = new \ReflectionClass($class);
        $attribute = $reflection->getAttributes(AsWorkflow::class)[0]->newInstance();

        return (new AttributeReader())->read($attribute, $reflection);
    }

    /**
     * @param list<array{string, int}> $from
     * @param list<array{string, int}> $to
     */
    private function assertTransition(TransitionDescriptor $transition, string $name, array $from, array $to, ?string $guard = null, array $metadata = []): void
    {
        $this->assertSame($name, $transition->name);
        $this->assertSame($from, array_map(static fn (Arc $arc) => [$arc->place, $arc->weight], $transition->from));
        $this->assertSame($to, array_map(static fn (Arc $arc) => [$arc->place, $arc->weight], $transition->to));
        $this->assertSame($guard, $transition->guard);
        $this->assertSame($metadata, $transition->metadata);
    }
}

enum TaskStep: string
{
    #[Place(metadata: ['label' => 'New'])]
    case New = 'new';

    #[Place(metadata: ['label' => 'Processing', 'bg_color' => '#eee'])]
    case Processing = 'processing';

    case Done = 'done';
    case Failed = 'failed';
    case Archived = 'archived';
}

enum IntStep: int
{
    case One = 1;
}

enum NamedStep: string
{
    #[Place('a')]
    case A = 'a';
}

class ValidatorWithRequiredArgument implements DefinitionValidatorInterface
{
    public function __construct(bool $enabled)
    {
    }

    public function validate(Definition $definition, string $name): void
    {
    }
}

#[AsWorkflow(supports: \stdClass::class)]
class TaskWorkflow
{
    #[Transition(from: 'new', to: 'processing')]
    public const START = 'start';

    #[Transition(from: 'processing', to: 'done')]
    public const FINISH = 'finish';
}

#[AsWorkflow(supports: \stdClass::class)]
class PullRequestStateMachine
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class OrderV2Workflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class HTMLPageWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class Reviewing
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow('explicit.name', supports: \stdClass::class)]
class NamedWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(
    name: 'full',
    type: WorkflowType::Workflow,
    supports: [\stdClass::class, \Countable::class],
    initialMarking: [TaskStep::New, 'extra'],
    markingProperty: 'step',
    metadata: ['title' => 'Full'],
    auditTrail: true,
    eventsToDispatch: ['!workflow.announce'],
    definitionValidators: [DefinitionValidator::class],
    places: [new Place('extra', metadata: ['label' => 'Extra']), TaskStep::Archived, 'orphan'],
    transitions: [
        new Transition('merge', from: [new Arc(TaskStep::Done, 2), 'extra'], to: TaskStep::Archived, guard: 'true', metadata: ['label' => 'Merge']),
    ],
)]
class FullWorkflow
{
    #[Transition(from: TaskStep::New, to: TaskStep::Processing, guard: 'is_granted("ROLE_USER")', metadata: ['color' => 'blue'])]
    public const START = 'start';

    #[Transition(from: TaskStep::Processing, to: TaskStep::Done)]
    #[Transition(from: TaskStep::Failed, to: TaskStep::Done)]
    public const FINISH = 'finish';
}

#[AsWorkflow(supportStrategy: 'app.support_strategy', markingStore: 'app.marking_store')]
class ServicesWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: TaskStep::class)]
class EnumPlacesWorkflow
{
    #[Transition(from: TaskStep::Done, to: TaskStep::Archived)]
    public const ARCHIVE = 'archive';
}

#[AsWorkflow(supports: \stdClass::class)]
class NameOnConstantWorkflow
{
    #[Transition('go', from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class IntConstantWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 1;
}

#[AsWorkflow(supports: \stdClass::class)]
class EmptyGuardWorkflow
{
    #[Transition(from: 'a', to: 'b', guard: '')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class EmptyFromWorkflow
{
    #[Transition(to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class InvalidArcWorkflow
{
    #[Transition(from: 'a', to: [1])]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class IntEnumWorkflow
{
    #[Transition(from: IntStep::One, to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, supportStrategy: 'app.support_strategy')]
class SupportsAndStrategyWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, markingProperty: 'step', markingStore: 'app.marking_store')]
class MarkingPropertyAndStoreWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: 'Missing\Subject')]
class MissingSupportWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, eventsToDispatch: ['workflow.unknown'])]
class UnknownEventWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, eventsToDispatch: [1])]
class NonStringEventWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, eventsToDispatch: ['workflow.enter', '!workflow.announce'])]
class MixedEventsWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, eventsToDispatch: ['!workflow.guard'])]
class DisabledGuardEventWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, definitionValidators: ['Missing\Validator'])]
class MissingValidatorWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, definitionValidators: [\DateTime::class])]
class WrongValidatorWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, definitionValidators: [ValidatorWithRequiredArgument::class])]
class ValidatorWithArgumentWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow('', supports: \stdClass::class)]
class EmptyNameWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class NoTransitionWorkflow
{
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: ['a', new Place('a')])]
class DuplicatePlaceWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: [new Place(metadata: ['label' => 'A'])])]
class PlaceWithoutNameWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: [1])]
class InvalidPlaceWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: \stdClass::class)]
class PlacesFromClassWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, places: TaskStep::class)]
class PlaceNotInEnumWorkflow
{
    #[Transition(from: TaskStep::New, to: 'foreign')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class NameOnCaseWorkflow
{
    #[Transition(from: NamedStep::A, to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, transitions: [new Transition(from: 'a', to: 'b')])]
class TransitionWithoutNameWorkflow
{
}

#[AsWorkflow(supports: \stdClass::class, transitions: ['go'])]
class InvalidTransitionWorkflow
{
}

#[AsWorkflow(supports: \stdClass::class, initialMarking: 'nope')]
class UnknownInitialPlaceWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class, initialMarking: [1])]
class InvalidInitialPlaceWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}
