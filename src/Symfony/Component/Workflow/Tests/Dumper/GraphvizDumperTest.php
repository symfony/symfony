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

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle, color="#FF0000", shape="doublecircle"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label="e", shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label="f", shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label="g", shape=circle];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f [label="t1", shape=box, shape="box", regular="1"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [label="t2", shape=box, shape="box", regular="1"];
  transition_4358694eeb098c6708ae914a10562ce722bbbc34 [label="t3", shape=box, shape="box", regular="1"];
  transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a [label="t4", shape=box, shape="box", regular="1"];
  transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 [label="t5", shape=box, shape="box", regular="1"];
  transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 [label="t6", shape=box, shape="box", regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_e5353879bd69bfddcb465dad176ff52db8319d6f [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d -> place_3c363836cf4e16666669a25da280a1865c2d2874 [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_4358694eeb098c6708ae914a10562ce722bbbc34 [style="solid"];
  transition_4358694eeb098c6708ae914a10562ce722bbbc34 -> place_58e6b3a414a1e090dfc6029add0f3555ccba127f [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a [style="solid"];
  transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a -> place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [style="solid"];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f -> transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 [style="solid"];
  transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 -> transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 [style="solid"];
  transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
}
';
    }

    public function createSimpleWorkflowDumpWithMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle, color="#FF0000", shape="doublecircle"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f [label="t1", shape=box, shape="box", regular="1"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [label="t2", shape=box, shape="box", regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_e5353879bd69bfddcb465dad176ff52db8319d6f [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }

    public function provideComplexWorkflowDumpWithoutMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label="e", shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label="f", shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label="g", shape=circle];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f [label="t1", shape=box, shape="box", regular="1"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [label="t2", shape=box, shape="box", regular="1"];
  transition_4358694eeb098c6708ae914a10562ce722bbbc34 [label="t3", shape=box, shape="box", regular="1"];
  transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a [label="t4", shape=box, shape="box", regular="1"];
  transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 [label="t5", shape=box, shape="box", regular="1"];
  transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 [label="t6", shape=box, shape="box", regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_e5353879bd69bfddcb465dad176ff52db8319d6f [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d -> place_3c363836cf4e16666669a25da280a1865c2d2874 [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_4358694eeb098c6708ae914a10562ce722bbbc34 [style="solid"];
  transition_4358694eeb098c6708ae914a10562ce722bbbc34 -> place_58e6b3a414a1e090dfc6029add0f3555ccba127f [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a [style="solid"];
  transition_a9dfb15be45a5f3128784c80c733f2cdee2f756a -> place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [style="solid"];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f -> transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 [style="solid"];
  transition_bf55e75fa263cbbc2529db49da43cb7f1d370b88 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 -> transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 [style="solid"];
  transition_e92a96c0e3a20d87ace74ab7871931a8f9f25943 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
}
';
    }

    public function provideSimpleWorkflowDumpWithoutMarking()
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f [label="t1", shape=box, shape="box", regular="1"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [label="t2", shape=box, shape="box", regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_e5353879bd69bfddcb465dad176ff52db8319d6f [style="solid"];
  transition_e5353879bd69bfddcb465dad176ff52db8319d6f -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_2a5bd02710e975a7fbb92da876655950fbd5e70d [style="solid"];
  transition_2a5bd02710e975a7fbb92da876655950fbd5e70d -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }
}
