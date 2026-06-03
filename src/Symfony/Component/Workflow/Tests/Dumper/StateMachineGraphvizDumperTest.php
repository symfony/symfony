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
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;

class StateMachineGraphvizDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testDumpWithoutMarking()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $dump = (new StateMachineGraphvizDumper())->dump($definition);

        $expected = <<<'EOGRAPH'
            digraph workflow {
              ratio="compress" rankdir="LR"
              node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
              edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

              place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle];
              place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
              place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
              place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
              place_3c363836cf4e16666669a25da280a1865c2d2874 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="My custom transition
            label 3" style="solid" fontcolor="Grey" color="Red"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="t2" style="solid" color="Blue"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_3c363836cf4e16666669a25da280a1865c2d2874 [label="t3" style="solid"];
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
              node [fontsize="9" fontname="Arial" color="#333333" fillcolor="lightblue" fixedsize="false" width="1"];
              edge [fontsize="9" fontname="Arial" color="#333333" arrowhead="normal" arrowsize="0.5"];

              place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 [label=<<B>a</B>>, shape=circle style="filled"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label=<<B>b</B>>, shape=circle color="#FF0000" shape="doublecircle"];
              place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label=<<B>c</B>>, shape=circle];
              place_3c363836cf4e16666669a25da280a1865c2d2874 [label=<<B>d</B>>, shape=circle];
              place_86f7e437faa5a7fce15d1ddcb9eaeaea377667b8 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="t1" style="solid"];
              place_3c363836cf4e16666669a25da280a1865c2d2874 -> place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 [label="My custom transition
            label 3" style="solid" fontcolor="Grey" color="Red"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_84a516841ba77a5b4648de2cd0dfcb30ea46dbb4 [label="t2" style="solid" color="Blue"];
              place_e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98 -> place_3c363836cf4e16666669a25da280a1865c2d2874 [label="t3" style="solid"];
            }

            EOGRAPH;

        $dump = (new StateMachineGraphvizDumper())->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }

    public function testDumpWithMetadata()
    {
        $places = ['open', 'in_progress', 'done'];
        $transitions = [];
        $transitions[] = new Transition('start', 'open', 'in_progress');
        $transitions[] = new Transition('finish', 'in_progress', 'done');

        $placesMetadata = [
            'open' => ['bg_color' => 'Gold', 'description' => 'Initial state'],
            'done' => ['bg_color' => 'LimeGreen'],
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], $placesMetadata);

        $definition = new Definition($places, $transitions, null, $inMemoryMetadataStore);
        $dump = (new StateMachineGraphvizDumper())->dump($definition, null, ['with-metadata' => true]);

        // bg_color should not appear as text, description should be in italics
        $this->assertStringContainsString('fillcolor="Gold"', $dump);
        $this->assertStringNotContainsString('bg_color', $dump);
        $this->assertStringContainsString('<I>Initial state</I>', $dump);
        $this->assertStringContainsString('fillcolor="LimeGreen"', $dump);
    }
}
