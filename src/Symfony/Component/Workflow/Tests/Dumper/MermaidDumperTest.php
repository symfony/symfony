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
     * @dataProvider provideStatemachine
     */
    public function testDumpAsStatemachine(Definition $definition, string $expected)
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

    public static function provideWorkflowDefinitionWithoutMarking(): array
    {
        return [
            [
                self::createComplexWorkflowDefinition(),
                "graph LR\n"
               ."a0([\"a\"])\n"
               ."b1((\"b\"))\n"
               ."c2((\"c\"))\n"
               ."d3((\"d\"))\n"
               ."e4((\"e\"))\n"
               ."f5((\"f\"))\n"
               ."g6((\"g\"))\n"
               ."transition0[\"t1\"]\n"
               ."a0-->transition0\n"
               ."transition0-->b1\n"
               ."transition0-->c2\n"
               ."transition1[\"t2\"]\n"
               ."b1-->transition1\n"
               ."transition1-->d3\n"
               ."c2-->transition1\n"
               ."transition2[\"My custom transition label 1\"]\n"
               ."d3-->transition2\n"
               ."linkStyle 6 stroke:Red\n"
               ."transition2-->e4\n"
               ."linkStyle 7 stroke:Red\n"
               ."transition3[\"t4\"]\n"
               ."d3-->transition3\n"
               ."transition3-->f5\n"
               ."transition4[\"t5\"]\n"
               ."e4-->transition4\n"
               ."transition4-->g6\n"
               ."transition5[\"t6\"]\n"
               ."f5-->transition5\n"
               .'transition5-->g6',
            ],
            [
                self::createWorkflowWithSameNameTransition(),
                "graph LR\n"
               ."a0([\"a\"])\n"
               ."b1((\"b\"))\n"
               ."c2((\"c\"))\n"
               ."transition0[\"a_to_bc\"]\n"
               ."a0-->transition0\n"
               ."transition0-->b1\n"
               ."transition0-->c2\n"
               ."transition1[\"b_to_c\"]\n"
               ."b1-->transition1\n"
               ."transition1-->c2\n"
               ."transition2[\"to_a\"]\n"
               ."b1-->transition2\n"
               ."transition2-->a0\n"
               ."transition3[\"to_a\"]\n"
               ."c2-->transition3\n"
               .'transition3-->a0',
            ],
            [
                self::createSimpleWorkflowDefinition(),
                "graph LR\n"
               ."a0([\"a\"])\n"
               ."b1((\"b\"))\n"
               ."c2((\"c\"))\n"
               ."style c2 fill:DeepSkyBlue\n"
               ."transition0[\"My custom transition label 2\"]\n"
               ."a0-->transition0\n"
               ."linkStyle 0 stroke:Grey\n"
               ."transition0-->b1\n"
               ."linkStyle 1 stroke:Grey\n"
               ."transition1[\"t2\"]\n"
               ."b1-->transition1\n"
               .'transition1-->c2',
            ],
        ];
    }

    public static function provideWorkflowWithReservedWords(): array
    {
        $builder = new DefinitionBuilder();

        $builder->addPlaces(['start', 'subgraph', 'end', 'finis']);
        $builder->addTransitions([
            new Transition('t0', ['start', 'subgraph'], ['end']),
            new Transition('t1', ['end'], ['finis']),
        ]);

        $definition = $builder->build();

        return [
            [
                $definition,
                "graph LR\n"
               ."start0([\"start\"])\n"
               ."subgraph1((\"subgraph\"))\n"
               ."end2((\"end\"))\n"
               ."finis3((\"finis\"))\n"
               ."transition0[\"t0\"]\n"
               ."start0-->transition0\n"
               ."transition0-->end2\n"
               ."subgraph1-->transition0\n"
               ."transition1[\"t1\"]\n"
               ."end2-->transition1\n"
               .'transition1-->finis3',
            ],
        ];
    }

    public static function provideStatemachine(): array
    {
        return [
            [
                self::createComplexStateMachineDefinition(),
                "graph LR\n"
               ."a0([\"a\"])\n"
               ."b1((\"b\"))\n"
               ."c2((\"c\"))\n"
               ."d3((\"d\"))\n"
               ."a0-->|\"t1\"|b1\n"
               ."d3-->|\"My custom transition label 3\"|b1\n"
               ."linkStyle 1 stroke:Grey\n"
               ."b1-->|\"t2\"|c2\n"
               .'b1-->|"t3"|d3',
            ],
        ];
    }

    public static function provideWorkflowWithMarking(): array
    {
        $marking = new Marking();
        $marking->mark('b');
        $marking->mark('c');

        return [
            [
                self::createSimpleWorkflowDefinition(),
                $marking,
                "graph LR\n"
                ."a0([\"a\"])\n"
                ."b1((\"b\"))\n"
                ."style b1 stroke-width:4px\n"
                ."c2((\"c\"))\n"
                ."style c2 fill:DeepSkyBlue,stroke-width:4px\n"
                ."transition0[\"My custom transition label 2\"]\n"
                ."a0-->transition0\n"
                ."linkStyle 0 stroke:Grey\n"
                ."transition0-->b1\n"
                ."linkStyle 1 stroke:Grey\n"
                ."transition1[\"t2\"]\n"
                ."b1-->transition1\n"
                .'transition1-->c2',
            ],
        ];
    }
}
