<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class TypeTestCase extends \PHPUnit_Framework_TestCase
{
    protected $storage;

    protected $factory;

    protected $builder;

    protected $dispatcher;

    protected $typeLoader;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new FormFactory($this->getExtensions());
        $this->builder = new FormBuilder(null, $this->factory, $this->dispatcher);
    }

    protected function getExtensions()
    {
        return array(
            new CoreExtension($this->storage),
        );
    }

    public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual)
    {
        self::assertEquals($expected->format('c'), $actual->format('c'));
    }
}
