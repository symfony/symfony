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
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\NullFormTypeGuesser;

class FormFactoryBuilderTest extends TestCase
{
    private $registry;
    private $type;

    protected function setUp(): void
    {
        $factory = new \ReflectionClass(FormFactory::class);
        $this->registry = $factory->getProperty('registry');

        $this->type = new FooType();
    }

    public function testAddType()
    {
        $factoryBuilder = new FormFactoryBuilder();
        $factoryBuilder->addType($this->type);

        $factory = $factoryBuilder->getFormFactory();
        $registry = $this->registry->getValue($factory);
        $extensions = $registry->getExtensions();

        $this->assertCount(1, $extensions);
        $this->assertTrue($extensions[0]->hasType($this->type::class));
        $this->assertNull($extensions[0]->getTypeGuesser());
    }

    public function testAddTypeGuesser()
    {
        $factoryBuilder = new FormFactoryBuilder();
        $factoryBuilder->addTypeGuesser(new NullFormTypeGuesser());

        $factory = $factoryBuilder->getFormFactory();
        $registry = $this->registry->getValue($factory);
        $extensions = $registry->getExtensions();

        $this->assertCount(1, $extensions);
        $this->assertNotNull($extensions[0]->getTypeGuesser());
    }
}
