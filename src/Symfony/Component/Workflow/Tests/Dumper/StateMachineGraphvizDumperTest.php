<?php

namespace Symfony\Component\Workflow\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class StateMachineGraphvizDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    private $dumper;

    protected function setUp()
    {
        $this->dumper = new StateMachineGraphvizDumper();
    }

    public function testDumpWithoutMarking()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $dump = $this->dumper->dump($definition);

        $expected = <<<'EOGRAPH'
digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle];
  place_c [label="c", shape=circle];
  place_d [label="d", shape=circle];
  place_a -> place_b [label="t1" style="solid"];
  place_d -> place_b [label="t1" style="solid"];
  place_b -> place_c [label="t2" style="solid"];
  place_b -> place_d [label="t3" style="solid"];
}

EOGRAPH;

        $this->assertEquals($expected, $dump);
    }

    public function testDumpWithMarking()
    {
        $definition = $this->createComplexStateMachineDefinition();
        $marking = new Marking(['b' => 1]);

        $expected = <<<'EOGRAPH'
digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle, color="#FF0000", shape="doublecircle"];
  place_c [label="c", shape=circle];
  place_d [label="d", shape=circle];
  place_a -> place_b [label="t1" style="solid"];
  place_d -> place_b [label="t1" style="solid"];
  place_b -> place_c [label="t2" style="solid"];
  place_b -> place_d [label="t3" style="solid"];
}

EOGRAPH;

        $dump = $this->dumper->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }
}
