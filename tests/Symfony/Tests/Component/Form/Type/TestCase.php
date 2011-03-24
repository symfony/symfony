<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $themeFactory;

    protected $csrfProvider;

    protected $validator;

    protected $storage;

    protected $em;

    protected $factory;

    protected $builder;

    protected $dispatcher;

    protected $chainLoader;

    protected function setUp()
    {
        $this->themeFactory = $this->getMock('Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface');
        $this->themeFactory->expects($this->any())
            ->method('create')
            ->will($this->returnValue($this->getMock('Symfony\Component\Form\Renderer\Theme\FormThemeInterface')));
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $loader = new DefaultTypeLoader($this->themeFactory, null,
                $this->validator, $this->csrfProvider, $this->storage);
        $this->chainLoader = new \Symfony\Component\Form\Type\Loader\TypeLoaderChain();
        $this->chainLoader->addLoader($loader);
        $this->factory = new FormFactory($this->chainLoader);

        $this->builder = new FormBuilder($this->dispatcher);
    }
}