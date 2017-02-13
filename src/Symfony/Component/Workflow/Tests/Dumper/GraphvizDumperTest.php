<?php

namespace Symfony\Component\Workflow\Tests\Dumper;

use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class GraphvizDumperTest extends \PHPUnit_Framework_TestCase
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
        yield array(
            $this->createComplexWorkflowDefinition(),
            new Marking(array('b' => 1)),
            $this->createComplexWorkflowDefinitionDumpWithMarking(),
        );

        yield array(
            $this->createSimpleWorkflowDefinition(),
            new Marking(array('c' => 1, 'd' => 1)),
            $this->createSimpleWorkflowDumpWithMarking(),
        );
    }

    public function provideWorkflowDefinitionWithoutMarking()
    {
        yield array($this->createComplexWorkflowDefinition(), $this->provideComplexWorkflowDumpWithoutMarking());
        yield array($this->createSimpleWorkflowDefinition(), $this->provideSimpleWorkflowDumpWithoutMarking());
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
  transition_t1 [label="t1", shape=box, shape="box", regular="1"];
  transition_t2 [label="t2", shape=box, shape="box", regular="1"];
  transition_t3 [label="t3", shape=box, shape="box", regular="1"];
  transition_t4 [label="t4", shape=box, shape="box", regular="1"];
  transition_t5 [label="t5", shape=box, shape="box", regular="1"];
  transition_t6 [label="t6", shape=box, shape="box", regular="1"];
  place_a -> transition_t1 [style="solid"];
  transition_t1 -> place_b [style="solid"];
  transition_t1 -> place_c [style="solid"];
  place_b -> transition_t2 [style="solid"];
  place_c -> transition_t2 [style="solid"];
  transition_t2 -> place_d [style="solid"];
  place_d -> transition_t3 [style="solid"];
  transition_t3 -> place_e [style="solid"];
  place_d -> transition_t4 [style="solid"];
  transition_t4 -> place_f [style="solid"];
  place_e -> transition_t5 [style="solid"];
  transition_t5 -> place_g [style="solid"];
  place_f -> transition_t6 [style="solid"];
  transition_t6 -> place_g [style="solid"];
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
  transition_t1 [label="t1", shape=box, shape="box", regular="1"];
  transition_t2 [label="t2", shape=box, shape="box", regular="1"];
  place_a -> transition_t1 [style="solid"];
  transition_t1 -> place_b [style="solid"];
  place_b -> transition_t2 [style="solid"];
  transition_t2 -> place_c [style="solid"];
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
  transition_t1 [label="t1", shape=box, shape="box", regular="1"];
  transition_t2 [label="t2", shape=box, shape="box", regular="1"];
  transition_t3 [label="t3", shape=box, shape="box", regular="1"];
  transition_t4 [label="t4", shape=box, shape="box", regular="1"];
  transition_t5 [label="t5", shape=box, shape="box", regular="1"];
  transition_t6 [label="t6", shape=box, shape="box", regular="1"];
  place_a -> transition_t1 [style="solid"];
  transition_t1 -> place_b [style="solid"];
  transition_t1 -> place_c [style="solid"];
  place_b -> transition_t2 [style="solid"];
  place_c -> transition_t2 [style="solid"];
  transition_t2 -> place_d [style="solid"];
  place_d -> transition_t3 [style="solid"];
  transition_t3 -> place_e [style="solid"];
  place_d -> transition_t4 [style="solid"];
  transition_t4 -> place_f [style="solid"];
  place_e -> transition_t5 [style="solid"];
  transition_t5 -> place_g [style="solid"];
  place_f -> transition_t6 [style="solid"];
  transition_t6 -> place_g [style="solid"];
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
  transition_t1 [label="t1", shape=box, shape="box", regular="1"];
  transition_t2 [label="t2", shape=box, shape="box", regular="1"];
  place_a -> transition_t1 [style="solid"];
  transition_t1 -> place_b [style="solid"];
  place_b -> transition_t2 [style="solid"];
  transition_t2 -> place_c [style="solid"];
}
';
    }
}
