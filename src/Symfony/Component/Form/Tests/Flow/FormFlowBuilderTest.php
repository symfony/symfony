<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Flow;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilder;
use Symfony\Component\Form\Flow\StepAccessor\PropertyPathStepAccessor;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

class FormFlowBuilderTest extends TestCase
{
    private FormFactoryInterface $factory;
    private InMemoryDataStorage $dataStorage;
    private PropertyPathStepAccessor $stepAccessor;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $this->dataStorage = new InMemoryDataStorage('key');
        $this->stepAccessor = new PropertyPathStepAccessor(PropertyAccess::createPropertyAccessor(), new PropertyPath('[currentStep]'));
    }

    public function testNoStepsConfigured()
    {
        $builder = new FormFlowBuilder('test', null, new EventDispatcher(), $this->factory);
        $builder->setData([]);
        $builder->setDataStorage($this->dataStorage);
        $builder->setStepAccessor($this->stepAccessor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Steps not configured.');

        $builder->getForm();
    }

    public function testRemoveAllStepsDynamically()
    {
        $builder = new FormFlowBuilder('test', null, new EventDispatcher(), $this->factory);
        $builder->setData([]);
        $builder->setDataStorage($this->dataStorage);
        $builder->setStepAccessor($this->stepAccessor);
        $builder->addStep('step1');

        // In a type extension context
        $builder->removeStep('step1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Steps not configured.');

        $builder->getForm();
    }

    public function testNestedFormFlowException()
    {
        // Create parent form flow builder
        $builder = new FormFlowBuilder('parent', null, new EventDispatcher(), $this->factory);
        $builder->setData([]);
        $builder->setDataStorage($this->dataStorage);
        $builder->setStepAccessor($this->stepAccessor);
        $builder->addStep('step1');

        // Create child form flow builder
        $childBuilder = new FormFlowBuilder('child', null, new EventDispatcher(), $this->factory);
        $childBuilder->setDataStorage(new InMemoryDataStorage('child_key'));
        $childBuilder->setStepAccessor($this->stepAccessor);
        $childBuilder->addStep('child_step1');

        // Add child form flow to parent
        $builder->add($childBuilder);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nested form flows is not currently supported.');

        $builder->getForm();
    }
}
