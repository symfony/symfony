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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;

class GraphvizDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    #[DataProvider('provideWorkflowDefinitionWithoutMarking')]
    public function testDumpWithoutMarking($definition, $expected, $withMetadata)
    {
        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => $withMetadata]);

        $this->assertEquals($expected, $dump);
    }

    #[DataProvider('provideWorkflowDefinitionWithMarking')]
    public function testDumpWithMarking($definition, $marking, $expected, $withMetadata)
    {
        $dump = (new GraphvizDumper())->dump($definition, $marking, ['with-metadata' => $withMetadata]);

        $this->assertEquals($expected, $dump);
    }

    public static function provideWorkflowDefinitionWithoutMarking(): \Generator
    {
        yield [self::createComplexWorkflowDefinition(), self::provideComplexWorkflowDumpWithoutMarking(), false];
        yield [self::createSimpleWorkflowDefinition(), self::provideSimpleWorkflowDumpWithoutMarking(), false];
        yield [self::createComplexWorkflowDefinition(), self::provideComplexWorkflowDumpWithoutMarkingWithMetadata(), true];
        yield [self::createSimpleWorkflowDefinition(), self::provideSimpleWorkflowDumpWithoutMarkingWithMetadata(), true];
    }

    public static function provideWorkflowDefinitionWithMarking(): \Generator
    {
        yield [
            self::createComplexWorkflowDefinition(),
            new Marking(['b' => 1]),
            self::createComplexWorkflowDefinitionDumpWithMarking(),
            false,
        ];

        yield [
            self::createSimpleWorkflowDefinition(),
            new Marking(['c' => 1, 'd' => 1]),
            self::createSimpleWorkflowDumpWithMarking(),
            false,
        ];

        yield [
            self::createComplexWorkflowDefinition(),
            new Marking(['b' => 1]),
            self::createComplexWorkflowDefinitionDumpWithMarkingAndMetadata(),
            true,
        ];

        yield [
            self::createSimpleWorkflowDefinition(),
            new Marking(['c' => 1, 'd' => 1]),
            self::createSimpleWorkflowDumpWithMarkingAndMetadata(),
            true,
        ];
    }

    public static function createComplexWorkflowDefinitionDumpWithMarking(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle color="#FF0000" shape="doublecircle"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label=<<B>e</B>>, shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label=<<B>f</B>>, shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label=<<B>g</B>>, shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>t1</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label=<<B>My custom transition label 1</B>>, shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label=<<B>t4</B>>, shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label=<<B>t5</B>>, shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label=<<B>t6</B>>, shape="box" regular="1"];
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

    public static function createComplexWorkflowDefinitionDumpWithMarkingAndMetadata(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle color="#FF0000" shape="doublecircle"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label=<<B>e</B>>, shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label=<<B>f</B>>, shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label=<<B>g</B>>, shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>t1</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label=<<B>My custom transition label 1</B><BR/>color: Red<BR/>arrow_color: Green>, shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label=<<B>t4</B>>, shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label=<<B>t5</B>>, shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label=<<B>t6</B>>, shape="box" regular="1"];
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

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle color="#FF0000" shape="doublecircle" style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>My custom transition label 2</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }

    public static function createSimpleWorkflowDumpWithMarkingAndMetadata(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B><BR/><I>My custom place description</I>>, shape=circle color="#FF0000" shape="doublecircle" style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>My custom transition label 2</B><BR/>color: Grey<BR/>arrow_color: Purple>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B><BR/>arrow_color: Pink>, shape="box" regular="1"];
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

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label=<<B>e</B>>, shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label=<<B>f</B>>, shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label=<<B>g</B>>, shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>t1</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label=<<B>My custom transition label 1</B>>, shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label=<<B>t4</B>>, shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label=<<B>t5</B>>, shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label=<<B>t6</B>>, shape="box" regular="1"];
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

    public static function provideComplexWorkflowDumpWithoutMarkingWithMetadata(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
  place_58e6b3a414a1e090dfc6029add0f3555ccba127f [label=<<B>e</B>>, shape=circle];
  place_4a0a19218e082a343a1b17e5333409af9d98f0f5 [label=<<B>f</B>>, shape=circle];
  place_54fd1711209fb1c0781092374132c66e79e2241b [label=<<B>g</B>>, shape=circle];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>t1</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label=<<B>My custom transition label 1</B><BR/>color: Red<BR/>arrow_color: Green>, shape="box" regular="1"];
  transition_77de68daecd823babbb58edb1c8e14d7106e83bb [label=<<B>t4</B>>, shape="box" regular="1"];
  transition_1b6453892473a467d07372d45eb05abc2031647a [label=<<B>t5</B>>, shape="box" regular="1"];
  transition_ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4 [label=<<B>t6</B>>, shape="box" regular="1"];
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

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>My custom transition label 2</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }

    public function testDumpEscapesSpecialCharactersInHtmlLabels()
    {
        $transitionWithSpecialChars = new Transition('t1', 'a', 'b');
        $transitions = [$transitionWithSpecialChars];

        $placesMetadata = [
            'a' => [
                'label' => 'A & B <tag>',
                'description' => 'has "quotes" & <html>',
            ],
        ];

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithSpecialChars] = [
            'label' => 'Run <script>alert(1)</script>',
            'note' => 'a > b & c < d',
        ];

        $store = new InMemoryMetadataStore([], $placesMetadata, $transitionsMetadata);
        $definition = new Definition(['a', 'b'], $transitions, null, $store);

        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => true, 'label' => 'Title with <b>bold</b> & "quotes"']);

        $this->assertStringContainsString('<<B>A &amp; B &lt;tag&gt;</B>', $dump);
        $this->assertStringContainsString('<BR/><I>has &quot;quotes&quot; &amp; &lt;html&gt;</I>', $dump);
        $this->assertStringContainsString('<<B>Run &lt;script&gt;alert(1)&lt;/script&gt;</B>', $dump);
        $this->assertStringContainsString('note: a &gt; b &amp; c &lt; d', $dump);
        $this->assertStringContainsString('<<B>Title with &lt;b&gt;bold&lt;/b&gt; &amp; &quot;quotes&quot;</B>', $dump);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $dump);
        $this->assertStringNotContainsString('<tag>', $dump);
    }

    public static function provideSimpleWorkflowDumpWithoutMarkingWithMetadata(): string
    {
        return 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B><BR/><I>My custom place description</I>>, shape=circle style="filled" fillcolor="DeepSkyBlue"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>My custom transition label 2</B><BR/>color: Grey<BR/>arrow_color: Purple>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B><BR/>arrow_color: Pink>, shape="box" regular="1"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
}
';
    }

    public function testDumpFiltersWorkflowLevelMetadata()
    {
        $workflowMetadata = [
            'bg_color' => 'Pink',
            'description' => 'Workflow description',
            'extra' => 'value',
        ];
        $store = new InMemoryMetadataStore($workflowMetadata);
        $definition = new Definition(['a', 'b'], [new Transition('t1', 'a', 'b')], null, $store);

        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => true]);

        $this->assertStringNotContainsString('bg_color', $dump);
        $this->assertStringContainsString('<BR/><I>Workflow description</I>', $dump);
        $this->assertStringContainsString('extra: value', $dump);

        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => true, 'label' => 'Title']);
        $this->assertStringNotContainsString('bg_color', $dump);
        $this->assertStringContainsString('<<B>Title</B>', $dump);
        $this->assertStringContainsString('<BR/><I>Workflow description</I>', $dump);
    }

    public function testDumpWithListenersFoldsListenersIntoTransitionMetadata()
    {
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition(['a', 'b'], [$transition]);

        $listeners = [
            'transition__0' => [
                'workflow.wf.transition.t1' => [
                    ['title' => 'App\\Listener\\OnTransition::__invoke()', 'file' => null],
                ],
                'workflow.wf.guard.t1' => [
                    ['title' => 'GuardListener', 'file' => null, 'guardExpressions' => ['is_granted("ROLE_USER")']],
                ],
            ],
        ];

        $dump = (new GraphvizDumper())->dump($definition, null, ['listeners' => $listeners]);

        $this->assertStringContainsString('Listener #0: App\\Listener\\OnTransition::__invoke()', $dump);
        $this->assertStringContainsString('Listener #1: Guard: is_granted(&quot;ROLE_USER&quot;)', $dump);
    }

    public function testDumpCoercesNonStringMetadataValuesInHtmlLabels()
    {
        $transition = new Transition('t1', 'a', 'b');
        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transition] = [
            'count' => 42,
            'ratio' => 1.5,
            'tags' => ['x', 'y'],
            'empty' => null,
        ];
        $store = new InMemoryMetadataStore([], [], $transitionsMetadata);
        $definition = new Definition(['a', 'b'], [$transition], null, $store);

        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => true]);

        $this->assertStringContainsString('count: 42', $dump);
        $this->assertStringContainsString('ratio: 1.5', $dump);
        $this->assertStringContainsString('tags: [&quot;x&quot;,&quot;y&quot;]', $dump);
    }

    public function testDumpWithMetadataEdgeCases()
    {
        $definition = self::createWorkflowWithMetadataEdgeCases();
        $dump = (new GraphvizDumper())->dump($definition, null, ['with-metadata' => true]);

        $expected = 'digraph workflow {
  ratio="compress" rankdir="LR"
  node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
  edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle style="filled" fillcolor="Orange"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B><BR/><I>A &lt;bold&gt; description with &quot;special&quot; chars &amp; entities</I>>, shape=circle];
  place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B><BR/><I>First line
Second line</I>>, shape=circle style="filled" fillcolor="Lime"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [label=<<B>t1</B>>, shape="box" regular="1"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab [label=<<B>t2</B>>, shape="box" regular="1"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [label=<<B>t3</B><BR/><I>Transition description</I>>, shape="box" regular="1" style="filled" fillcolor="LightCoral"];
  place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c [style="solid"];
  transition_b6589fc6ab0dc82cf12099d1c2d40ab994e8410c -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [style="solid"];
  place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> transition_356a192b7913b04c54574d18c28d46e6395428ab [style="solid"];
  transition_356a192b7913b04c54574d18c28d46e6395428ab -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [style="solid"];
  place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 -> transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 [style="solid"];
  transition_da4b9237bacccdf19c0760cab7aec4a8359010b0 -> place_3c363836cf4e16666669a25da280a1865c2d2874 [style="solid"];
}
';

        $this->assertEquals($expected, $dump);
    }
}
