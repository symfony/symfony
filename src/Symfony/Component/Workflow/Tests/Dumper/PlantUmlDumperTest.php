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
use Symfony\Component\Workflow\Dumper\PlantUmlDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class PlantUmlDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testDumpWorkflowWithoutMarking($definition, $marking, $expectedFileName, $title)
    {
        $dumper = new PlantUmlDumper(PlantUmlDumper::WORKFLOW_TRANSITION);
        $dump = $dumper->dump($definition, $marking, array('title' => $title));
        // handle windows, and avoid to create more fixtures
        $dump = str_replace(PHP_EOL, "\n", $dump.PHP_EOL);
        $file = $this->getFixturePath($expectedFileName, PlantUmlDumper::WORKFLOW_TRANSITION);
        $this->assertStringEqualsFile($file, $dump);
    }

    public function provideWorkflowDefinitionWithoutMarking()
    {
        yield array($this->createSimpleWorkflowDefinition(), null, 'simple-workflow-nomarking', 'SimpleDiagram');
        yield array($this->createComplexWorkflowDefinition(), null, 'complex-workflow-nomarking', 'ComplexDiagram');
        $marking = new Marking(array('b' => 1));
        yield array($this->createSimpleWorkflowDefinition(), $marking, 'simple-workflow-marking', 'SimpleDiagram');
        $marking = new Marking(array('c' => 1, 'e' => 1));
        yield array($this->createComplexWorkflowDefinition(), $marking, 'complex-workflow-marking', 'ComplexDiagram');
    }

    /**
     * @dataProvider provideStateMachineDefinitionWithoutMarking
     */
    public function testDumpStateMachineWithoutMarking($definition, $marking, $expectedFileName, $title)
    {
        $dumper = new PlantUmlDumper(PlantUmlDumper::STATEMACHINE_TRANSITION);
        $dump = $dumper->dump($definition, $marking, array('title' => $title));
        // handle windows, and avoid to create more fixtures
        $dump = str_replace(PHP_EOL, "\n", $dump.PHP_EOL);
        $file = $this->getFixturePath($expectedFileName, PlantUmlDumper::STATEMACHINE_TRANSITION);
        $this->assertStringEqualsFile($file, $dump);
    }

    public function provideStateMachineDefinitionWithoutMarking()
    {
        yield array($this->createComplexStateMachineDefinition(), null, 'complex-state-machine-nomarking', 'SimpleDiagram');
        $marking = new Marking(array('c' => 1, 'e' => 1));
        yield array($this->createComplexStateMachineDefinition(), $marking, 'complex-state-machine-marking', 'SimpleDiagram');
    }

    private function getFixturePath($name, $transitionType)
    {
        return __DIR__.'/../fixtures/puml/'.$transitionType.'/'.$name.'.puml';
    }
}
