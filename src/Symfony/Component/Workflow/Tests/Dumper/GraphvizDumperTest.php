<?php

namespace Symfony\Component\Workflow\Tests\Dumper;

use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;

class GraphvizDumperTest extends \PHPUnit_Framework_TestCase
{
    private $dumper;

    public function setUp()
    {
        $this->dumper = new GraphvizDumper();
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testGraphvizDumperWithoutMarking($definition, $expected)
    {
        $dump = $this->dumper->dump($definition);

        $this->assertEquals($expected, $dump);
    }

    /**
     * @dataProvider provideWorkflowDefinitionWithMarking
     */
    public function testWorkflowWithMarking($definition, $marking, $expected)
    {
        $dump = $this->dumper->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }

    public function provideWorkflowDefinitionWithMarking()
    {
        yield array(
            $this->provideComplexWorkflowDefinition(),
            new Marking(array('b' => 1)),
            $this->createComplexWorkflowDumpWithMarking(),
        );

        yield array(
            $this->provideSimpleWorkflowDefinition(),
            new Marking(array('c' => 1, 'd' => 1)),
            $this->createSimpleWorkflowDumpWithMarking(),
        );
    }

    public function provideWorkflowDefinitionWithoutMarking()
    {
        yield array($this->provideComplexWorkflowDefinition(), $this->provideComplexWorkflowDumpWithoutMarking());
        yield array($this->provideSimpleWorkflowDefinition(), $this->provideSimpleWorkflowDumpWithoutMarking());
    }

    public function provideComplexWorkflowDefinition()
    {
        $builder = new DefinitionBuilder();

        $builder->addPlaces(range('a', 'g'));

        $builder->addTransition('t1', 'a', array('b', 'c'));
        $builder->addTransition('t2', array('b', 'c'), 'd');
        $builder->addTransition('t3', 'd', 'e');
        $builder->addTransition('t4', 'd', 'f');
        $builder->addTransition('t5', 'e', 'g');
        $builder->addTransition('t6', 'f', 'g');

        return $builder->build();
    }

    public function provideSimpleWorkflowDefinition()
    {
        $builder = new DefinitionBuilder();

        $builder->addPlaces(range('a', 'c'));

        $builder->addTransition('t1', 'a', 'b');
        $builder->addTransition('t2', 'b', 'c');

        return $builder->build();
    }

    public function createComplexWorkflowDumpWithMarking()
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
