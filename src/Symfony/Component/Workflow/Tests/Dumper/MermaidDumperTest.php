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
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;

class MermaidDumperTest extends TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @dataProvider provideWorkflowDefinitionWithoutMarking
     */
    public function testDumpWithoutMarking(Definition $definition, string $expected)
    {
        $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_WORKFLOW);

        $dump = $dumper->dump($definition);

        $this->assertEquals($expected, $dump);
    }

    /**
     * @dataProvider provideWorkflowWithReservedWords
     */
    public function testDumpWithReservedWordsAsPlacenames(Definition $definition, string $expected)
    {
        $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_WORKFLOW);

        $dump = $dumper->dump($definition);

        $this->assertEquals($expected, $dump);
    }

    /**
     * @dataProvider provideStateMachine
     */
    public function testDumpAsStateMachine(Definition $definition, string $expected)
    {
        $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_STATEMACHINE);

        $dump = $dumper->dump($definition);

        $this->assertEquals($expected, $dump);
    }

    /**
     * @dataProvider provideWorkflowWithMarking
     */
    public function testDumpWorkflowWithMarking(Definition $definition, Marking $marking, string $expected)
    {
        $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_WORKFLOW);

        $dump = $dumper->dump($definition, $marking);

        $this->assertEquals($expected, $dump);
    }

    public static function provideWorkflowDefinitionWithoutMarking(): iterable
    {
        yield [
            self::createComplexWorkflowDefinition(),
            "graph LR\n"
            ."place0([\"a\"])\n"
            ."place1((\"b\"))\n"
            ."place2((\"c\"))\n"
            ."place3((\"d\"))\n"
            ."place4((\"e\"))\n"
            ."place5((\"f\"))\n"
            ."place6((\"g\"))\n"
            ."transition0[\"t1\"]\n"
            ."place0-->transition0\n"
            ."transition0-->place1\n"
            ."transition0-->place2\n"
            ."transition1[\"t2\"]\n"
            ."place1-->transition1\n"
            ."transition1-->place3\n"
            ."place2-->transition1\n"
            ."transition2[\"My custom transition label 1\"]\n"
            ."place3-->transition2\n"
            ."linkStyle 6 stroke:Red\n"
            ."transition2-->place4\n"
            ."linkStyle 7 stroke:Red\n"
            ."transition3[\"t4\"]\n"
            ."place3-->transition3\n"
            ."transition3-->place5\n"
            ."transition4[\"t5\"]\n"
            ."place4-->transition4\n"
            ."transition4-->place6\n"
            ."transition5[\"t6\"]\n"
            ."place5-->transition5\n"
            ."transition5-->place6",
        ];
        yield [
            self::createWorkflowWithSameNameTransition(),
            "graph LR\n"
            ."place0([\"a\"])\n"
            ."place1((\"b\"))\n"
            ."place2((\"c\"))\n"
            ."transition0[\"a_to_bc\"]\n"
            ."place0-->transition0\n"
            ."transition0-->place1\n"
            ."transition0-->place2\n"
            ."transition1[\"b_to_c\"]\n"
            ."place1-->transition1\n"
            ."transition1-->place2\n"
            ."transition2[\"to_a\"]\n"
            ."place1-->transition2\n"
            ."transition2-->place0\n"
            ."transition3[\"to_a\"]\n"
            ."place2-->transition3\n"
            ."transition3-->place0",
        ];
        yield [
            self::createSimpleWorkflowDefinition(),
            "graph LR\n"
            ."place0([\"a\"])\n"
            ."place1((\"b\"))\n"
            ."place2((\"c\"))\n"
            ."style place2 fill:DeepSkyBlue\n"
            ."transition0[\"My custom transition label 2\"]\n"
            ."place0-->transition0\n"
            ."linkStyle 0 stroke:Grey\n"
            ."transition0-->place1\n"
            ."linkStyle 1 stroke:Grey\n"
            ."transition1[\"t2\"]\n"
            ."place1-->transition1\n"
            ."transition1-->place2",
        ];
    }

    public static function provideWorkflowWithReservedWords(): iterable
    {
        $builder = new DefinitionBuilder();

        $builder->addPlaces(['start', 'subgraph', 'end', 'finis']);
        $builder->addTransitions([
            new Transition('t0', ['start', 'subgraph'], ['end']),
            new Transition('t1', ['end'], ['finis']),
        ]);

        $definition = $builder->build();

        yield [
            $definition,
            "graph LR\n"
            ."place0([\"start\"])\n"
            ."place1((\"subgraph\"))\n"
            ."place2((\"end\"))\n"
            ."place3((\"finis\"))\n"
            ."transition0[\"t0\"]\n"
            ."place0-->transition0\n"
            ."transition0-->place2\n"
            ."place1-->transition0\n"
            ."transition1[\"t1\"]\n"
            ."place2-->transition1\n"
            ."transition1-->place3",
        ];
    }

    public static function provideStateMachine(): iterable
    {
        yield [
            self::createComplexStateMachineDefinition(),
            "graph LR\n"
            ."place0([\"a\"])\n"
            ."place1((\"b\"))\n"
            ."place2((\"c\"))\n"
            ."place3((\"d\"))\n"
            ."place0-->|\"t1\"|place1\n"
            ."place3-->|\"My custom transition label 3\"|place1\n"
            ."linkStyle 1 stroke:Grey\n"
            ."place1-->|\"t2\"|place2\n"
            ."place1-->|\"t3\"|place3",
        ];
    }

    public static function provideWorkflowWithMarking(): iterable
    {
        $marking = new Marking();
        $marking->mark('b');
        $marking->mark('c');

        yield [
            self::createSimpleWorkflowDefinition(),
            $marking,
            "graph LR\n"
            ."place0([\"a\"])\n"
            ."place1((\"b\"))\n"
            ."style place1 stroke-width:4px\n"
            ."place2((\"c\"))\n"
            ."style place2 fill:DeepSkyBlue,stroke-width:4px\n"
            ."transition0[\"My custom transition label 2\"]\n"
            ."place0-->transition0\n"
            ."linkStyle 0 stroke:Grey\n"
            ."transition0-->place1\n"
            ."linkStyle 1 stroke:Grey\n"
            ."transition1[\"t2\"]\n"
            ."place1-->transition1\n"
            ."transition1-->place2",
        ];
    }
}
