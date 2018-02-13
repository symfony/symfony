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

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="t2" style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_3c363836cf4e16666669a25da280a1865c2d2874 [label="t3" style="solid"];
}

EOGRAPH;

        $this->assertEquals($expected, $dump);
    }

    public function testDumpWithMarking()
    {
        $definition = $this->createComplexStateMachineDefinition();
        $marking = new Marking(array('b' => 1));

        $expected = <<<'EOGRAPH'
digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="1" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle, style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle, color="#FF0000", shape="doublecircle"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="t2" style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_3c363836cf4e16666669a25da280a1865c2d2874 [label="t3" style="solid"];
}

EOGRAPH;

        $dump = $this->dumper->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }
}
