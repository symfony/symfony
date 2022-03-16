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
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

abstract class AbstractFormTest extends TestCase
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var MockObject&FormFactoryInterface
     */
    protected $factory;

    /**
     * @var FormInterface
     */
    protected $form;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->createMock(FormFactoryInterface::class);
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

    protected function getDataMapper(): MockObject&DataMapperInterface
    {
        return $this->createMock(DataMapperInterface::class);
    }

    protected function getDataTransformer(): MockObject&DataTransformerInterface
    {
        return $this->createMock(DataTransformerInterface::class);
    }
}
