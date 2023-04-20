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
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;

class GraphvizDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    private $dumper;

    protected function setUp(): void
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

    public static function provideWorkflowDefinitionWithMarking(): \Generator
    {
        yield [
            self::createComplexWorkflowDefinition(),
            new Marking(['b' => 1]),
            self::createComplexWorkflowDefinitionDumpWithMarking(),
        ];

        yield [
            self::createSimpleWorkflowDefinition(),
            new Marking(['c' => 1, 'd' => 1]),
            self::createSimpleWorkflowDumpWithMarking(),
        ];
    }

    public static function provideWorkflowDefinitionWithoutMarking(): \Generator
    {
        yield [self::createComplexWorkflowDefinition(), self::provideComplexWorkflowDumpWithoutMarking()];
        yield [self::createSimpleWorkflowDefinition(), self::provideSimpleWorkflowDumpWithoutMarking()];
    }

    public static function createComplexWorkflowDefinitionDumpWithMarking(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle color="#FF0000" shape="doublecircle"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label="e", shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label="f", shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label="g", shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label="t1", shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label="t2", shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label="My custom transition label 1", shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label="t4", shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label="t5", shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label="t6", shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_3c363836cf4e16666669a25da280a1865c2d2874 [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [style="solid"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 -> place_58e6b3a414a1e090dfc6029add0f3555ccba127f [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_77de68daecd823babbb58edb1c8e14d7106e83bb [style="solid"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb -> place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [style="solid"];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f -> transition_1b6453892473a467d07372d45eb05abc2031647a [style="solid"];
  transition_1b6453892473a467d07372d45eb05abc2031647a -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 -> transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [style="solid"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
}
';
    }

    public static function createSimpleWorkflowDumpWithMarking(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle color="#FF0000" shape="doublecircle" style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label="My custom transition label 2", shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label="t2", shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }

    public static function provideComplexWorkflowDumpWithoutMarking(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label="d", shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label="e", shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label="f", shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label="g", shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label="t1", shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label="t2", shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label="My custom transition label 1", shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label="t4", shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label="t5", shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label="t6", shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_3c363836cf4e16666669a25da280a1865c2d2874 [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [style="solid"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 -> place_58e6b3a414a1e090dfc6029add0f3555ccba127f [style="solid"];
  place_3c363836cf4e16666669a25da280a1865c2d2874 -> transition_77de68daecd823babbb58edb1c8e14d7106e83bb [style="solid"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb -> place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [style="solid"];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f -> transition_1b6453892473a467d07372d45eb05abc2031647a [style="solid"];
  transition_1b6453892473a467d07372d45eb05abc2031647a -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 -> transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [style="solid"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 -> place_54fd1711209fb1c0781092374132c66e79e2241b [style="solid"];
}
';
    }

    public static function provideSimpleWorkflowDumpWithoutMarking(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label="a", shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="b", shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="c", shape=circle style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label="My custom transition label 2", shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label="t2", shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }
}
