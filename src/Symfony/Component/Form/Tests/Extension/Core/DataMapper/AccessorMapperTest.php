<?php

// Declare strict is necessary here to provoke type errors
declare(strict_types = 1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataMapper;

use PHPUnit\Framework\MockObject\Matcher\Invocation;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Extension\Core\DataMapper\AccessorMapper;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

class AccessorMapperTest extends TestCase
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var PropertyPathMapper
     */
    private $propertyPathMapper;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->propertyPathMapper = $this->getMockBuilder(DataMapperInterface::class)->getMock();
    }

    public function testGetUsesFallbackIfNoAccessor()
    {
        $this->setupPropertyPathMapper($this->once(), $this->never());

        $data = $this->getMockBuilder(stdClass::class)->addMethods(['getEngine', 'getEngineClosure'])->getMock();
        $data
            ->expects($this->never())
            ->method('getEngineClosure');
        $data
            ->expects($this->once())
            ->method('getEngine')
            ->willReturn('electric');

        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $mapper = $this->createMapper(false, false);
        $mapper->mapDataToForms($data, [$form]);

        $this->assertSame('electric', $form->getData());
    }

    public function testGetUsesAccessor()
    {
        $this->setupPropertyPathMapper($this->never(), $this->never());

        $data = $this->getMockBuilder(stdClass::class)->addMethods(['getEngine', 'getEngineClosure'])->getMock();
        $data
            ->expects($this->once())
            ->method('getEngineClosure')
            ->willReturn('electric');
        $data
            ->expects($this->never())
            ->method('getEngine');

        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $mapper = $this->createMapper(true, false);
        $mapper->mapDataToForms($data, [$form]);

        $this->assertSame('electric', $form->getData());
    }

    public function testSetUsesAccessor()
    {
        $this->setupPropertyPathMapper($this->never(), $this->never());

        $data = new class() {
            private $engine;

            public function setEngineClosure($engine)
            {
                $this->engine = $engine;
            }

            public function getEngine()
            {
                return $this->engine;
            }
        };

        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $form->submit('electric');
        $mapper = $this->createMapper(false, true);
        $mapper->mapFormsToData([$form], $data);

        $this->assertSame('electric', $data->getEngine());
    }

    public function testSetUsesAccessorForCompoundFields()
    {
        $this->setupPropertyPathMapper($this->any(), $this->any());

        $data = new class() {
            private $foo;
            private $bar;

            public function setEngineClosure($data)
            {
                if (!$data) {
                    return;
                }

                foreach ($data as $key => $value) {
                    $this->$key = $value;
                }
            }

            public function getEngineClosure()
            {
                return [
                    'foo' => $this->foo,
                    'bar' => $this->bar,
                ];
            }

            public function getFoo()
            {
                return $this->foo;
            }

            public function getBar()
            {
                return $this->bar;
            }
        };

        $config = new FormConfigBuilder('address', null, $this->dispatcher);
        $config->setCompound(true);
        $config->setDataMapper(new PropertyPathMapper($this->propertyAccessor));
        $addressForm = new Form($config);
        $addressForm
            ->add(new Form(new FormConfigBuilder('foo', null, $this->dispatcher)))
            ->add(new Form(new FormConfigBuilder('bar', null, $this->dispatcher)));

        $mapper = $this->createMapper(true, true);

        $config = new FormConfigBuilder('name', null, $this->dispatcher);
        $config->setCompound(true);
        $config->setDataMapper($mapper);
        $config->setData($data);
        $form = new Form($config);
        $form->add($addressForm);

        $form->submit(['address' => ['foo' => 'foo', 'bar' => 'bar']]);
        $this->assertNull($form->getTransformationFailure());

        $this->assertSame('foo', $data->getFoo());
        $this->assertSame('bar', $data->getBar());
    }

    public function testSetAccessorSupportsImmutableObjects()
    {
        $this->setupPropertyPathMapper($this->any(), $this->any());

        $data = new class('petrol') {
            private $engine;

            public function __construct(string $engine)
            {
                $this->engine = $engine;
            }

            public function setEngineClosure($data)
            {
                return new self($data);
            }

            public function getEngineClosure()
            {
                return $this->engine;
            }
        };

        $config = new FormConfigBuilder('car', null, $this->dispatcher);
        $config->setCompound(true);
        $config->setDataMapper($this->createMapper(true, true));
        $config->setData($data);
        $form = new Form($config);
        $form
            ->add(new Form(new FormConfigBuilder('engine', null, $this->dispatcher)));

        $form->submit(['engine' => 'electric']);
        $this->assertNull($form->getTransformationFailure());

        $formData = $form->getData();

        $this->assertNotSame($data, $formData);
        $this->assertSame('electric', $formData->getEngineClosure());
        $this->assertSame('petrol', $data->getEngineClosure());
    }

    public static function invalidValueProvider(): \Generator
    {
        yield 'validation error' => ['#Corn is not a valid engine type#', 'corn'];
        yield 'type error' => ['#Argument 1 passed to class@anonymous::setEngineClosure\(\) must be of the type string, object given#', new stdClass()];
    }

    /**
     * @dataProvider InvalidValueProvider
     */
    public function testSetAccessorCatchesExceptions(string $errorMessagePattern, $value)
    {
        $this->setupPropertyPathMapper($this->any(), $this->any());

        $data = new class('petrol') {
            private $engine;

            public function __construct(string $engine)
            {
                $this->engine = $engine;
            }

            public function setEngineClosure(string $data)
            {
                if ($data === 'corn') {
                    throw new class extends RuntimeException
                    {
                        public function __construct()
                        {
                            parent::__construct('Corn is not a valid engine type');
                        }
                    };
                }

                $this->engine = $data;
            }

            public function getEngineClosure()
            {
                return $this->engine;
            }
        };

        $config = new FormConfigBuilder('car', null, $this->dispatcher);
        $config->setCompound(true);
        $config->setDataMapper($this->createMapper(true, true));
        $config->setData($data);
        $form = new Form($config);
        $form
            ->add(new Form(new FormConfigBuilder('engine', null, $this->dispatcher)));

        $form->submit(['engine' => $value]);
        $this->assertFalse($form->isValid());
        $this->assertSame('petrol', $data->getEngineClosure());

        $this->assertMatchesRegularExpression($errorMessagePattern, (string) $form->get('engine')->getErrors());
    }

    private function setupPropertyPathMapper(Invocation $dataToFormsMatcher, Invocation $formsToDataMatcher): void
    {
        $propertyPathMapper = new PropertyPathMapper($this->propertyAccessor);

        $this->propertyPathMapper
            ->expects($dataToFormsMatcher)
            ->method('mapDataToForms')
            ->willReturnCallback(function (...$args) use ($propertyPathMapper) {
                $propertyPathMapper->mapDataToForms(...$args);
            });
        $this->propertyPathMapper
            ->expects($formsToDataMatcher)
            ->method('mapFormsToData')
            ->willReturnCallback(function (...$args) use ($propertyPathMapper) {
                $propertyPathMapper->mapFormsToData(...$args);
            });
    }

    private function createMapper(bool $useGetClosure, bool $useSetClosure)
    {
        return new AccessorMapper(
            $useGetClosure ? function ($object) { return $object->getEngineClosure(); } : null,
            $useSetClosure ? function ($object, $value) { return $object->setEngineClosure($value); } : null,
            $this->propertyPathMapper
        );
    }
}
