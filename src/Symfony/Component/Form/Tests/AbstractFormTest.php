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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;

abstract class AbstractFormTest extends TestCase
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    protected $factory;

    /**
     * @var FormInterface
     */
    protected $form;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $this->form = $this->createForm();
    }

    protected function tearDown(): void
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    abstract protected function createForm(): FormInterface;

    protected function getBuilder(?string $name = 'name', EventDispatcherInterface $dispatcher = null, string $dataClass = null, array $options = []): FormBuilder
    {
        return new FormBuilder($name, $dataClass, $dispatcher ?: $this->dispatcher, $this->factory, $options);
    }

    protected function getDataMapper(): MockObject
    {
        return $this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock();
    }

    protected function getDataTransformer(): MockObject
    {
        return $this->getMockBuilder('Symfony\Component\Form\DataTransformerInterface')->getMock();
    }

    protected function getFormValidator(): MockObject
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormValidatorInterface')->getMock();
    }
}
