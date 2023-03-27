<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\PlantUmlDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;

class PlantUmlDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testDumpWorkflowWithoutMarking($definition, $marking, $expectedFileName, $title)
    {
        $dumper = new PlantUmlDumper(PlantUmlDumper::WORKFLOW_TRANSITION);
        $dump = $dumper->dump($definition, $marking, ['title' => $title]);
        // handle windows, and avoid to create more fixtures
        $dump = str_replace(\PHP_EOL, "\n", $dump.\PHP_EOL);
        $file = $this->getFixturePath($expectedFileName, PlantUmlDumper::WORKFLOW_TRANSITION);
        $this->assertStringEqualsFile($file, $dump);
    }

    public static function provideWorkflowDefinitionWithoutMarking(): \Generator
    {
        yield [self::createSimpleWorkflowDefinition(), null, 'simple-workflow-nomarking', 'SimpleDiagram'];
        yield [self::createComplexWorkflowDefinition(), null, 'complex-workflow-nomarking', 'ComplexDiagram'];
        $marking = new Marking(['b' => 1]);
        yield [self::createSimpleWorkflowDefinition(), $marking, 'simple-workflow-marking', 'SimpleDiagram'];
        $marking = new Marking(['c' => 1, 'e' => 1]);
        yield [self::createComplexWorkflowDefinition(), $marking, 'complex-workflow-marking', 'ComplexDiagram'];
    }

    /**
     * @dataProvider provideStateMachineDefinitionWithoutMarking
     */
    public function testDumpStateMachineWithoutMarking($definition, $marking, $expectedFileName, $title)
    {
        $dumper = new PlantUmlDumper(PlantUmlDumper::STATEMACHINE_TRANSITION);
        $dump = $dumper->dump($definition, $marking, ['title' => $title]);
        // handle windows, and avoid to create more fixtures
        $dump = str_replace(\PHP_EOL, "\n", $dump.\PHP_EOL);
        $file = $this->getFixturePath($expectedFileName, PlantUmlDumper::STATEMACHINE_TRANSITION);
        $this->assertStringEqualsFile($file, $dump);
    }

    public static function provideStateMachineDefinitionWithoutMarking(): \Generator
    {
        yield [static::createComplexStateMachineDefinition(), null, 'complex-state-machine-nomarking', 'SimpleDiagram'];
        $marking = new Marking(['c' => 1, 'e' => 1]);
        yield [static::createComplexStateMachineDefinition(), $marking, 'complex-state-machine-marking', 'SimpleDiagram'];
    }

    public function testDumpWorkflowWithSpacesInTheStateNamesAndDescription()
    {
        $dumper = new PlantUmlDumper(PlantUmlDumper::WORKFLOW_TRANSITION);

        // The graph looks like:
        //
        // +---------+  t 1   +----------+  |
        // | place a | -----> | place b  |  |
        // +---------+        +----------+  |
        $places = ['place a', 'place b'];

        $transitions = [];
        $transition = new Transition('t 1', 'place a', 'place b');
        $transitions[] = $transition;

        $placesMetadata = [];
        $placesMetadata['place a'] = [
            'description' => 'My custom place description',
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], $placesMetadata);
        $definition = new Definition($places, $transitions, null, $inMemoryMetadataStore);

        $dump = $dumper->dump($definition, null, ['title' => 'SimpleDiagram']);
        $dump = str_replace(\PHP_EOL, "\n", $dump.\PHP_EOL);
        $file = $this->getFixturePath('simple-workflow-with-spaces', PlantUmlDumper::WORKFLOW_TRANSITION);
        $this->assertStringEqualsFile($file, $dump);
    }

    private function getFixturePath($name, $transitionType): string
    {
        return __DIR__.'/../fixtures/puml/'.$transitionType.'/'.$name.'.puml';
    }
}
