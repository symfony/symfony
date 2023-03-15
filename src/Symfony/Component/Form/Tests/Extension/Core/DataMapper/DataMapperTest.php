<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataMapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Tests\Fixtures\TypehintedPropertiesCar;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

class DataMapperTest extends TestCase
{
    /**
     * @var DataMapper
     */
    private $mapper;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp(): void
    {
        $this->mapper = new DataMapper();
        $this->dispatcher = new EventDispatcher();
    }

    public function testMapDataToFormsPassesObjectRefIfByReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $car->engine = $engine;
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $this->mapper->mapDataToForms($car, new \ArrayIterator([$form]));

        self::assertSame($engine, $form->getData());
    }

    public function testMapDataToFormsPassesObjectCloneIfNotByReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $engine->brand = 'Rolls-Royce';
        $car->engine = $engine;
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(false);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $this->mapper->mapDataToForms($car, new \ArrayIterator([$form]));

        self::assertNotSame($engine, $form->getData());
        self::assertEquals($engine, $form->getData());
    }

    public function testMapDataToFormsIgnoresEmptyPropertyPath()
    {
        $car = new \stdClass();

        $config = new FormConfigBuilder(null, \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $form = new Form($config);

        self::assertNull($form->getPropertyPath());

        $this->mapper->mapDataToForms($car, new \ArrayIterator([$form]));

        self::assertNull($form->getData());
    }

    public function testMapDataToFormsIgnoresUnmapped()
    {
        $car = new \stdClass();
        $car->engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setMapped(false);
        $config->setPropertyPath($propertyPath);
        $form = new Form($config);

        $this->mapper->mapDataToForms($car, new \ArrayIterator([$form]));

        self::assertNull($form->getData());
    }

    public function testMapDataToFormsIgnoresUninitializedProperties()
    {
        $engineForm = new Form(new FormConfigBuilder('engine', null, $this->dispatcher));
        $colorForm = new Form(new FormConfigBuilder('color', null, $this->dispatcher));

        $car = new TypehintedPropertiesCar();
        $car->engine = 'BMW';

        $this->mapper->mapDataToForms($car, new \ArrayIterator([$engineForm, $colorForm]));

        self::assertSame($car->engine, $engineForm->getData());
        self::assertNull($colorForm->getData());
    }

    public function testMapDataToFormsSetsDefaultDataIfPassedDataIsNull()
    {
        $default = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($default);

        $form = new Form($config);

        $this->mapper->mapDataToForms(null, new \ArrayIterator([$form]));

        self::assertSame($default, $form->getData());
    }

    public function testMapDataToFormsSetsDefaultDataIfPassedDataIsEmptyArray()
    {
        $default = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($default);

        $form = new Form($config);

        $this->mapper->mapDataToForms([], new \ArrayIterator([$form]));

        self::assertSame($default, $form->getData());
    }

    public function testMapFormsToDataWritesBackIfNotByReference()
    {
        $car = new \stdClass();
        $car->engine = new \stdClass();
        $engine = new \stdClass();
        $engine->brand = 'Rolls-Royce';
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(false);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertEquals($engine, $car->engine);
        self::assertNotSame($engine, $car->engine);
    }

    public function testMapFormsToDataWritesBackIfByReferenceButNoReference()
    {
        $car = new \stdClass();
        $car->engine = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($engine, $car->engine);
    }

    public function testMapFormsToDataWritesBackIfByReferenceAndReference()
    {
        $car = new \stdClass();
        $car->engine = 'BMW';
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('engine', null, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData('Rolls-Royce');
        $form = new SubmittedForm($config);

        $car->engine = 'Rolls-Royce';

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame('Rolls-Royce', $car->engine);
    }

    public function testMapFormsToDataIgnoresUnmapped()
    {
        $initialEngine = new \stdClass();
        $car = new \stdClass();
        $car->engine = $initialEngine;
        $engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $config->setMapped(false);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresUnsubmittedForms()
    {
        $initialEngine = new \stdClass();
        $car = new \stdClass();
        $car->engine = $initialEngine;
        $engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new Form($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresEmptyData()
    {
        $initialEngine = new \stdClass();
        $car = new \stdClass();
        $car->engine = $initialEngine;
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData(null);
        $form = new Form($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresUnsynchronized()
    {
        $initialEngine = new \stdClass();
        $car = new \stdClass();
        $car->engine = $initialEngine;
        $engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new NotSynchronizedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresDisabled()
    {
        $initialEngine = new \stdClass();
        $car = new \stdClass();
        $car->engine = $initialEngine;
        $engine = new \stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', \stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $config->setDisabled(true);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToUninitializedProperties()
    {
        $car = new TypehintedPropertiesCar();
        $config = new FormConfigBuilder('engine', null, $this->dispatcher);
        $config->setData('BMW');
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $car);

        self::assertSame('BMW', $car->engine);
    }

    /**
     * @dataProvider provideDate
     */
    public function testMapFormsToDataDoesNotChangeEqualDateTimeInstance($date)
    {
        $article = [];
        $publishedAt = $date;
        $publishedAtValue = clone $publishedAt;
        $article['publishedAt'] = $publishedAtValue;
        $propertyPath = new PropertyPath('[publishedAt]');

        $config = new FormConfigBuilder('publishedAt', $publishedAt::class, $this->dispatcher);
        $config->setByReference(false);
        $config->setPropertyPath($propertyPath);
        $config->setData($publishedAt);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $article);

        self::assertSame($publishedAtValue, $article['publishedAt']);
    }

    public static function provideDate(): array
    {
        return [
            [new \DateTime()],
            [new \DateTimeImmutable()],
        ];
    }

    public function testMapDataToFormsUsingGetCallbackOption()
    {
        $initialName = 'John Doe';
        $person = new DummyPerson($initialName);

        $config = new FormConfigBuilder('name', null, $this->dispatcher, [
            'getter' => static fn (DummyPerson $person) => $person->myName(),
        ]);
        $form = new Form($config);

        $this->mapper->mapDataToForms($person, new \ArrayIterator([$form]));

        self::assertSame($initialName, $form->getData());
    }

    public function testMapFormsToDataUsingSetCallbackOption()
    {
        $person = new DummyPerson('John Doe');

        $config = new FormConfigBuilder('name', null, $this->dispatcher, [
            'setter' => static function (DummyPerson $person, $name) {
                $person->rename($name);
            },
        ]);
        $config->setData('Jane Doe');
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new \ArrayIterator([$form]), $person);

        self::assertSame('Jane Doe', $person->myName());
    }

    public function testMapFormsToDataMapsDateTimeInstanceToArrayIfNotSetBefore()
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();
        $form = (new FormFactoryBuilder())->getFormFactory()->createBuilder()
            ->setDataMapper(new DataMapper(new PropertyPathAccessor($propertyAccessor)))
            ->add('date', DateType::class, [
                'auto_initialize' => false,
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
                'widget' => 'single_text',
            ])
            ->getForm();

        $form->submit([
            'date' => '04/08/2022',
        ]);

        $this->assertEquals(['date' => new \DateTime('2022-08-04', new \DateTimeZone('UTC'))], $form->getData());
    }
}

class SubmittedForm extends Form
{
    public function isSubmitted(): bool
    {
        return true;
    }
}

class NotSynchronizedForm extends SubmittedForm
{
    public function isSynchronized(): bool
    {
        return false;
    }
}

class DummyPerson
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function myName(): string
    {
        return $this->name;
    }

    public function rename($name): void
    {
        $this->name = $name;
    }
}
