<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;

class DefinitionBuilderTest extends TestCase
{
    public function testSetInitialPlaces()
    {
        $builder = new DefinitionBuilder(['a', 'b']);
        $builder->setInitialPlaces('b');
        $definition = $builder->build();

        self::assertEquals(['b'], $definition->getInitialPlaces());
    }

    public function testAddTransition()
    {
        $places = range('a', 'b');

        $transition0 = new Transition('name0', $places[0], $places[1]);
        $transition1 = new Transition('name1', $places[0], $places[1]);
        $builder = new DefinitionBuilder($places, [$transition0]);
        $builder->addTransition($transition1);

        $definition = $builder->build();

        self::assertCount(2, $definition->getTransitions());
        self::assertSame($transition0, $definition->getTransitions()[0]);
        self::assertSame($transition1, $definition->getTransitions()[1]);
    }

    public function testAddPlace()
    {
        $builder = new DefinitionBuilder(['a'], []);
        $builder->addPlace('b');

        $definition = $builder->build();

        self::assertCount(2, $definition->getPlaces());
        self::assertEquals('a', $definition->getPlaces()['a']);
        self::assertEquals('b', $definition->getPlaces()['b']);
    }

    public function testSetMetadataStore()
    {
        $builder = new DefinitionBuilder(['a']);
        $metadataStore = new InMemoryMetadataStore();
        $builder->setMetadataStore($metadataStore);
        $definition = $builder->build();

        self::assertSame($metadataStore, $definition->getMetadataStore());
    }
}
