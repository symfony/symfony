<?php

namespace Symfony\Component\Workflow\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class GraphvizDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    private $dumper;

    protected function setUp()
    {
        $this->dumper = new GraphvizDumper();
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testDumpWithoutMarking($definition, $expected)
    {
        $dump = $this->dumper->dump($definition);

        $this->assertEquals($expected, $dump);
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithMarking
     */
    public function testDumpWithMarking($definition, $marking, $expected)
    {
        $dump = $this->dumper->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }

    public function provideWorkflowDefinitionWithMarking()
    {
        yield [
            $this->createComplexWorkflowDefinition(),
            new Marking(['b' => 1]),
            $this->createComplexWorkflowDefinitionDumpWithMarking(),
        ];

        yield [
            $this->createSimpleWorkflowDefinition(),
            new Marking(['c' => 1, 'd' => 1]),
            $this->createSimpleWorkflowDumpWithMarking(),
        ];
    }

    public function provideWorkflowDefinitionWithoutMarking()
    {
        yield [$this->createComplexWorkflowDefinition(), $this->provideComplexWorkflowDumpWithoutMarking()];
        yield [$this->createSimpleWorkflowDefinition(), $this->provideSimpleWorkflowDumpWithoutMarking()];
    }

    public function createComplexWorkflowDefinitionDumpWithMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle, color="#FF0000", shape="doublecircle"];
  place_c [label="c", shape=circle];
  place_d [label="d", shape=circle];
  place_e [label="e", shape=circle];
  place_f [label="f", shape=circle];
  place_g [label="g", shape=circle];
  transition_0 [label="t1", shape=box, shape="box", regular="1"];
  transition_1 [label="t2", shape=box, shape="box", regular="1"];
  transition_2 [label="t3", shape=box, shape="box", regular="1"];
  transition_3 [label="t4", shape=box, shape="box", regular="1"];
  transition_4 [label="t5", shape=box, shape="box", regular="1"];
  transition_5 [label="t6", shape=box, shape="box", regular="1"];
  place_a -> transition_0 [style="solid"];
  transition_0 -> place_b [style="solid"];
  transition_0 -> place_c [style="solid"];
  place_b -> transition_1 [style="solid"];
  place_c -> transition_1 [style="solid"];
  transition_1 -> place_d [style="solid"];
  place_d -> transition_2 [style="solid"];
  transition_2 -> place_e [style="solid"];
  place_d -> transition_3 [style="solid"];
  transition_3 -> place_f [style="solid"];
  place_e -> transition_4 [style="solid"];
  transition_4 -> place_g [style="solid"];
  place_f -> transition_5 [style="solid"];
  transition_5 -> place_g [style="solid"];
}
';
    }

    public function createSimpleWorkflowDumpWithMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle];
  place_c [label="c", shape=circle, color="#FF0000", shape="doublecircle"];
  transition_0 [label="t1", shape=box, shape="box", regular="1"];
  transition_1 [label="t2", shape=box, shape="box", regular="1"];
  place_a -> transition_0 [style="solid"];
  transition_0 -> place_b [style="solid"];
  place_b -> transition_1 [style="solid"];
  transition_1 -> place_c [style="solid"];
}
';
    }

    public function provideComplexWorkflowDumpWithoutMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle];
  place_c [label="c", shape=circle];
  place_d [label="d", shape=circle];
  place_e [label="e", shape=circle];
  place_f [label="f", shape=circle];
  place_g [label="g", shape=circle];
  transition_0 [label="t1", shape=box, shape="box", regular="1"];
  transition_1 [label="t2", shape=box, shape="box", regular="1"];
  transition_2 [label="t3", shape=box, shape="box", regular="1"];
  transition_3 [label="t4", shape=box, shape="box", regular="1"];
  transition_4 [label="t5", shape=box, shape="box", regular="1"];
  transition_5 [label="t6", shape=box, shape="box", regular="1"];
  place_a -> transition_0 [style="solid"];
  transition_0 -> place_b [style="solid"];
  transition_0 -> place_c [style="solid"];
  place_b -> transition_1 [style="solid"];
  place_c -> transition_1 [style="solid"];
  transition_1 -> place_d [style="solid"];
  place_d -> transition_2 [style="solid"];
  transition_2 -> place_e [style="solid"];
  place_d -> transition_3 [style="solid"];
  transition_3 -> place_f [style="solid"];
  place_e -> transition_4 [style="solid"];
  transition_4 -> place_g [style="solid"];
  place_f -> transition_5 [style="solid"];
  transition_5 -> place_g [style="solid"];
}
';
    }

    public function provideSimpleWorkflowDumpWithoutMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_a [label="a", shape=circle, style="filled"];
  place_b [label="b", shape=circle];
  place_c [label="c", shape=circle];
  transition_0 [label="t1", shape=box, shape="box", regular="1"];
  transition_1 [label="t2", shape=box, shape="box", regular="1"];
  place_a -> transition_0 [style="solid"];
  transition_0 -> place_b [style="solid"];
  place_b -> transition_1 [style="solid"];
  transition_1 -> place_c [style="solid"];
}
';
    }
}
