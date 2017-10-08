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
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Tests\Fixtures\FooType;

class FormFactoryBuilderTest extends TestCase
{
    private $registry;
    private $guesser;
    private $type;

    protected function setUp()
    {
        $factory = new \ReflectionClass('Symfony\Component\Form\FormFactory');
        $this->registry = $factory->getProperty('registry');
        $this->registry->setAccessible(true);

        $this->guesser = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
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
        $this->assertTrue($extensions[0]->hasType(get_class($this->type)));
        $this->assertNull($extensions[0]->getTypeGuesser());
    }

    public function testAddTypeGuesser()
    {
        $factoryBuilder = new FormFactoryBuilder();
        $factoryBuilder->addTypeGuesser($this->guesser);

        $factory = $factoryBuilder->getFormFactory();
        $registry = $this->registry->getValue($factory);
        $extensions = $registry->getExtensions();

        $this->assertCount(1, $extensions);
        $this->assertNotNull($extensions[0]->getTypeGuesser());
    }
}
