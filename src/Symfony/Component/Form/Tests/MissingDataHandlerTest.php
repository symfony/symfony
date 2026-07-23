<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\MissingDataHandler;
use Symfony\Component\Form\ResolvedFormTypeFactory;

class MissingDataHandlerTest extends TestCase
{
    public function testFalseValuesMatchIsStrict()
    {
        $handler = new MissingDataHandler();

        $parent = $this->createBuilder('parent', ['false_values' => [false]])
            ->setCompound(true)
            ->setDataMapper(new DataMapper())
            ->add($this->createBuilder('child', ['false_values' => [null]]))
            ->getForm();

        $this->assertSame(['child' => null], $handler->handle($parent, []));
    }

    public function testEmptyArrayRoundTripsWhenNoChildSynthesises()
    {
        $handler = new MissingDataHandler();

        $parent = $this->createCompound('parent')
            ->add($this->createBuilder('text'))
            ->getForm();

        $this->assertSame([], $handler->handle($parent, []));
    }

    public function testMissingDataSentinelSurvivesWhenNoChildSynthesises()
    {
        $handler = new MissingDataHandler();

        $parent = $this->createCompound('parent')
            ->add($this->createBuilder('text'))
            ->getForm();

        $this->assertSame($handler->missingData, $handler->handle($parent, $handler->missingData));
    }

    public function testTextSiblingIsNotSynthesisedAlongsideCheckbox()
    {
        $handler = new MissingDataHandler();

        $parent = $this->createCompound('parent')
            ->add($this->createBuilder('text'))
            ->add($this->createBuilder('checkbox', ['false_values' => [null]]))
            ->getForm();

        $this->assertSame(['checkbox' => null], $handler->handle($parent, []));
    }

    public function testUnknownKeysArePreservedAlongsideSynthesisedEntries()
    {
        $handler = new MissingDataHandler();

        $parent = $this->createCompound('parent')
            ->add($this->createBuilder('checkbox', ['false_values' => [null]]))
            ->getForm();

        $this->assertSame(['known' => 'x', 'checkbox' => null], $handler->handle($parent, ['known' => 'x']));
    }

    public function testNestedCompoundSynthesisesDeepCheckbox()
    {
        $handler = new MissingDataHandler();

        $deep = $this->createCompound('deep')
            ->add($this->createBuilder('checkbox', ['false_values' => [null]]));

        $parent = $this->createCompound('parent')
            ->add($deep)
            ->getForm();

        $this->assertSame(['deep' => ['checkbox' => null]], $handler->handle($parent, []));
    }

    private function createBuilder(string $name, array $options = []): FormBuilder
    {
        return new FormBuilder($name, null, new EventDispatcher(), new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory())), $options);
    }

    private function createCompound(string $name, array $options = []): FormBuilder
    {
        return $this->createBuilder($name, $options)
            ->setCompound(true)
            ->setDataMapper(new DataMapper());
    }
}
