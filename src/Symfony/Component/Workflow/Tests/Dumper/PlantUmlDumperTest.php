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
use Symfony\Component\Workflow\Dumper\DumperInterface;
use Symfony\Component\Workflow\Dumper\PlantUmlDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class PlantUmlDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @var DumperInterface[]
     */
    private $dumpers;

    protected function setUp()
    {
        $this->dumpers =
            array(
                PlantUmlDumper::STATEMACHINE_TRANSITION => new PlantUmlDumper(PlantUmlDumper::STATEMACHINE_TRANSITION),
                PlantUmlDumper::WORKFLOW_TRANSITION => new PlantUmlDumper(PlantUmlDumper::WORKFLOW_TRANSITION),
            );
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testDumpWithoutMarking($definition, $expectedFileName, $title, $nofooter)
    {
        foreach ($this->dumpers as $transitionType => $dumper) {
            $dump = $dumper->dump($definition, null, array('title' => $title, 'nofooter' => $nofooter));
            // handle windows, and avoid to create more fixtures
            $dump = str_replace(PHP_EOL, "\n", $dump.PHP_EOL);
            $this->assertStringEqualsFile($this->getFixturePath($expectedFileName, $transitionType), $dump);
        }
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithMarking
     */
    public function testDumpWithMarking($definition, $marking, $expectedFileName, $title, $footer)
    {
        foreach ($this->dumpers as $transitionType => $dumper) {
            $dump = $dumper->dump($definition, $marking, array('title' => $title, 'nofooter' => $footer));
            // handle windows, and avoid to create more fixtures
            $dump = str_replace(PHP_EOL, "\n", $dump.PHP_EOL);
            $this->assertStringEqualsFile($this->getFixturePath($expectedFileName, $transitionType), $dump);
        }
    }

    public function provideWorkflowDefinitionWithoutMarking()
    {
        $title = 'SimpleDiagram';
        yield array($this->createSimpleWorkflowDefinition(), 'simple-workflow-nomarking-nofooter', $title, true);
        yield array($this->createSimpleWorkflowDefinition(), 'simple-workflow-nomarking', $title, false);
        $title = 'ComplexDiagram';
        yield array($this->createComplexWorkflowDefinition(), 'complex-workflow-nomarking-nofooter', $title, true);
        yield array($this->createComplexWorkflowDefinition(), 'complex-workflow-nomarking', $title, false);
    }

    public function provideWorkflowDefinitionWithMarking()
    {
        $title = 'SimpleDiagram';
        $marking = new Marking(array('b' => 1));
        yield array(
            $this->createSimpleWorkflowDefinition(), $marking, 'simple-workflow-marking-nofooter', $title, true,
        );
        yield array(
            $this->createSimpleWorkflowDefinition(), $marking, 'simple-workflow-marking', $title, false,
        );
        $title = 'ComplexDiagram';
        $marking = new Marking(array('c' => 1, 'e' => 1));
        yield array(
            $this->createComplexWorkflowDefinition(), $marking, 'complex-workflow-marking-nofooter', $title, true,
        );
        yield array(
            $this->createComplexWorkflowDefinition(), $marking, 'complex-workflow-marking', $title, false,
        );
    }

    private function getFixturePath($name, $transitionType)
    {
        return __DIR__.'/../fixtures/puml/'.$transitionType.'/'.$name.'.puml';
    }
}
