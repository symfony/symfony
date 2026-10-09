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
use Symfony\Component\Workflow\DependencyInjection\AttributeReader;
use Symfony\Component\Workflow\Tests\Fixtures\DefinitionValidator;
use Symfony\Component\Workflow\WorkflowType;

class AttributeReaderTest extends TestCase
{
    public function testMinimalDefinition()
    {
        $this->assertSame(['task' => [
            'type' => 'state_machine',
            'supports' => \stdClass::class,
            'initial_marking' => [],
            'metadata' => [],
            'audit_trail' => ['enabled' => false],
            'definition_validators' => [],
            'places' => [
                ['name' => 'new', 'metadata' => []],
                ['name' => 'processing', 'metadata' => []],
                ['name' => 'done', 'metadata' => []],
            ],
            'transitions' => [
                ['name' => 'start', 'from' => [['place' => 'new']], 'to' => [['place' => 'processing']], 'metadata' => []],
                ['name' => 'finish', 'from' => [['place' => 'processing']], 'to' => [['place' => 'done']], 'metadata' => []],
            ],
        ]], $this->read(TaskWorkflow::class));
    }

    #[DataProvider('provideNames')]
    public function testNameIsDerivedFromTheClassName(string $class, string $expectedName)
    {
        $this->assertSame($expectedName, array_key_first($this->read($class)));
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
        $this->assertSame(['full' => [
            'type' => 'workflow',
            'supports' => [\stdClass::class, \Countable::class],
            'initial_marking' => [TaskStep::New, 'extra'],
            'metadata' => ['title' => 'Full'],
            'audit_trail' => ['enabled' => true],
            'definition_validators' => [DefinitionValidator::class],
            // The places inferred from the transitions first, then the ones that no transition uses
            'places' => [
                ['name' => 'new', 'metadata' => ['label' => 'New']],
                ['name' => 'processing', 'metadata' => ['label' => 'Processing', 'bg_color' => '#eee']],
                ['name' => 'done', 'metadata' => []],
                ['name' => 'failed', 'metadata' => []],
                ['name' => 'extra', 'metadata' => ['label' => 'Extra']],
                ['name' => 'archived', 'metadata' => []],
                ['name' => 'orphan', 'metadata' => []],
            ],
            'transitions' => [
                ['name' => 'start', 'from' => [['place' => 'new']], 'to' => [['place' => 'processing']], 'metadata' => ['color' => 'blue'], 'guard' => 'is_granted("ROLE_USER")'],
                ['name' => 'finish', 'from' => [['place' => 'processing']], 'to' => [['place' => 'done']], 'metadata' => []],
                ['name' => 'finish', 'from' => [['place' => 'failed']], 'to' => [['place' => 'done']], 'metadata' => []],
                ['name' => 'merge', 'from' => [['place' => 'done', 'weight' => 2], ['place' => 'extra']], 'to' => [['place' => 'archived']], 'metadata' => ['label' => 'Merge'], 'guard' => 'true'],
            ],
            'marking_store' => ['property' => 'step'],
            'events_to_dispatch' => ['!workflow.announce'],
        ]], $this->read(FullWorkflow::class));
    }

    public function testSupportStrategyAndMarkingStore()
    {
        $workflow = $this->read(ServicesWorkflow::class)['services'];

        $this->assertSame([], $workflow['supports']);
        $this->assertSame('app.support_strategy', $workflow['support_strategy']);
        $this->assertSame(['service' => 'app.marking_store'], $workflow['marking_store']);
    }

    public function testPlacesFromAnEnum()
    {
        $workflow = $this->read(EnumPlacesWorkflow::class)['enum_places'];

        $this->assertSame([
            ['name' => 'new', 'metadata' => ['label' => 'New']],
            ['name' => 'processing', 'metadata' => ['label' => 'Processing', 'bg_color' => '#eee']],
            ['name' => 'done', 'metadata' => []],
            ['name' => 'failed', 'metadata' => []],
            ['name' => 'archived', 'metadata' => []],
        ], $workflow['places']);
    }

    public function testPlacesFromTheConstantsOfTheClass()
    {
        $workflow = $this->read(PlaceConstantsWorkflow::class)['place_constants'];

        $this->assertSame([
            ['name' => 'draft', 'metadata' => ['label' => 'Draft']],
            ['name' => 'published', 'metadata' => []],
            ['name' => 'archived', 'metadata' => []],
        ], $workflow['places']);
    }

    #[DataProvider('provideInitialMarkings')]
    public function testInitialMarking(string $class, \BackedEnum|array $expectedInitialMarking)
    {
        $this->assertSame($expectedInitialMarking, current($this->read($class))['initial_marking']);
    }

    public static function provideInitialMarkings(): iterable
    {
        yield 'no initial place' => [TaskWorkflow::class, []];
        yield 'initial enum case' => [InitialCaseStateMachine::class, ['draft']];
        yield 'initial constant' => [InitialConstantStateMachine::class, ['pending']];
        yield 'several initial places' => [InitialPlacesWorkflow::class, ['draft', 'review']];
        yield 'explicit initial marking' => [ExplicitInitialMarkingStateMachine::class, ArticleStatus::Published];
        yield 'initial enum case not used by the workflow' => [UnusedInitialCaseStateMachine::class, []];
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

        yield 'empty name' => [EmptyNameWorkflow::class, 'The name of the workflow defined by "'.$ns.'EmptyNameWorkflow" cannot be empty.'];
        yield 'non-string transition constant' => [IntConstantWorkflow::class, 'The value of "'.$ns.'IntConstantWorkflow::GO" must be a string to be used as the name of a transition, "int" given.'];
        yield 'state machine transition from several places' => [JoinStateMachine::class, 'The "go" transition of the state machine defined by "'.$ns.'JoinStateMachine" must have exactly one input and one output place; repeat the "#[Symfony\Component\Workflow\Attribute\Transition]" attribute to define several transitions with the same name.'];
        yield 'state machine transition to several places' => [SplitStateMachine::class, 'The "go" transition of the state machine defined by "'.$ns.'SplitStateMachine" must have exactly one input and one output place; repeat the "#[Symfony\Component\Workflow\Attribute\Transition]" attribute to define several transitions with the same name.'];
        yield 'invalid arc' => [InvalidArcWorkflow::class, 'The places of the "go" transition of the workflow defined by "'.$ns.'InvalidArcWorkflow" must be "Symfony\Component\Workflow\Arc" instances, enum cases or strings, "int" given.'];
        yield 'int-backed enum' => [IntEnumWorkflow::class, 'Only string-backed enums can be used as places of the workflow defined by "'.$ns.'IntEnumWorkflow", "'.$ns.'IntStep" is not.'];
        yield 'non-string place constant' => [IntPlaceConstantWorkflow::class, 'The value of "'.$ns.'IntPlaceConstantWorkflow::ONE" must be a string or a string-backed enum case to be used as the name of a place, "int" given.'];
        yield 'duplicate place' => [DuplicatePlaceWorkflow::class, 'The place "a" of the workflow defined by "'.$ns.'DuplicatePlaceWorkflow" is defined by both "'.$ns.'DuplicatePlaceWorkflow::A" and "'.$ns.'DuplicatePlaceWorkflow::B".'];
        yield 'places from a class' => [PlacesFromClassWorkflow::class, 'The "places" argument of "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" on "'.$ns.'PlacesFromClassWorkflow" must be the name of a string-backed enum, "stdClass" given.'];
        yield 'place not in the enum' => [PlaceNotInEnumWorkflow::class, 'The place "foreign" of the workflow defined by "'.$ns.'PlaceNotInEnumWorkflow" is not a case of "'.$ns.'TaskStep".'];
    }

    private function read(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $attribute = $reflection->getAttributes(AsWorkflow::class)[0]->newInstance();

        return (new AttributeReader())->read($attribute, $reflection);
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

enum ArticleStatus: string
{
    #[Place(initial: true)]
    case Draft = 'draft';

    case Published = 'published';
    case Archived = 'archived';
}

enum IntStep: int
{
    case One = 1;
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
)]
class FullWorkflow
{
    #[Place(metadata: ['label' => 'Extra'])]
    public const EXTRA = 'extra';

    #[Place]
    public const ORPHAN = 'orphan';

    #[Transition(from: TaskStep::New, to: TaskStep::Processing, guard: 'is_granted("ROLE_USER")', metadata: ['color' => 'blue'])]
    public const START = 'start';

    #[Transition(from: TaskStep::Processing, to: TaskStep::Done)]
    #[Transition(from: TaskStep::Failed, to: TaskStep::Done)]
    public const FINISH = 'finish';

    #[Transition(from: [new Arc(TaskStep::Done->value, 2), self::EXTRA], to: TaskStep::Archived, guard: 'true', metadata: ['label' => 'Merge'])]
    public const MERGE = 'merge';
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
class PlaceConstantsWorkflow
{
    #[Place]
    public const ARCHIVED = 'archived';

    #[Place(metadata: ['label' => 'Draft'])]
    public const DRAFT = 'draft';

    #[Transition(from: self::DRAFT, to: 'published')]
    public const PUBLISH = 'publish';
}

#[AsWorkflow(supports: \stdClass::class)]
class IntConstantWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 1;
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

#[AsWorkflow(supports: \stdClass::class)]
class JoinStateMachine
{
    #[Transition(from: ['a', 'b'], to: 'c')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class SplitStateMachine
{
    #[Transition(from: 'a', to: ['b', 'c'])]
    public const GO = 'go';
}

#[AsWorkflow('', supports: \stdClass::class)]
class EmptyNameWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class DuplicatePlaceWorkflow
{
    #[Place]
    public const A = 'a';

    #[Place]
    public const B = 'a';

    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class IntPlaceConstantWorkflow
{
    #[Place]
    public const ONE = 1;

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
class InitialCaseStateMachine
{
    #[Transition(from: ArticleStatus::Published, to: ArticleStatus::Archived)]
    public const ARCHIVE = 'archive';

    #[Transition(from: ArticleStatus::Draft, to: ArticleStatus::Published)]
    public const PUBLISH = 'publish';
}

#[AsWorkflow(supports: \stdClass::class)]
class InitialConstantStateMachine
{
    #[Place(initial: true)]
    public const PENDING = 'pending';

    #[Transition(from: 'new', to: self::PENDING)]
    public const SUBMIT = 'submit';
}

#[AsWorkflow(type: WorkflowType::Workflow, supports: \stdClass::class)]
class InitialPlacesWorkflow
{
    #[Place(initial: true)]
    public const REVIEW = 'review';

    #[Transition(from: [ArticleStatus::Draft, self::REVIEW], to: ArticleStatus::Published)]
    public const PUBLISH = 'publish';
}

#[AsWorkflow(supports: \stdClass::class, initialMarking: ArticleStatus::Published)]
class ExplicitInitialMarkingStateMachine
{
    #[Transition(from: ArticleStatus::Draft, to: ArticleStatus::Published)]
    public const PUBLISH = 'publish';
}

#[AsWorkflow(supports: \stdClass::class)]
class UnusedInitialCaseStateMachine
{
    #[Transition(from: ArticleStatus::Published, to: ArticleStatus::Archived)]
    public const ARCHIVE = 'archive';
}
