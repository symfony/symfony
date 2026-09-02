<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\AsFormType\AdminData;
use Symfony\Component\Form\Tests\Fixtures\AsFormType\CarData;
use Symfony\Component\Form\Tests\Fixtures\AsFormType\UserData;
use Symfony\Component\Form\Tests\Fixtures\AsFormType\VehicleData;

class DataClassFormIntegrationTest extends TestCase
{
    public function testCreateFormFromDataClass()
    {
        $factory = $this->createFormFactory();

        $user = new UserData();
        $form = $factory->create(UserData::class, $user);

        $this->assertSame(['name', 'bio', 'publicName'], array_keys($form->all()));
        $this->assertSame('User', $form->getConfig()->getOption('label'));
        $this->assertSame(UserData::class, $form->getConfig()->getOption('data_class'));
        $this->assertContains('user_data', $form->createView()->vars['block_prefixes']);

        $form->submit(['name' => 'Ada', 'bio' => 'Hi', 'publicName' => 'ada']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Ada', $user->name);
        $this->assertSame('Hi', $user->bio);
        $this->assertSame('ada', $user->internalName);
    }

    public function testFieldsAreInheritedThroughTheParentType()
    {
        $factory = $this->createFormFactory();

        $admin = new AdminData();
        $form = $factory->create(AdminData::class, $admin);

        $this->assertSame(['name', 'bio', 'publicName', 'role'], array_keys($form->all()));
        $this->assertSame(AdminData::class, $form->getConfig()->getOption('data_class'));

        $form->submit(['name' => 'Ada', 'bio' => 'Hi', 'publicName' => 'ada', 'role' => 'admin']);

        $this->assertSame('admin', $admin->role);
        $this->assertSame('Ada', $admin->name);
    }

    public function testAbstractParentDataClass()
    {
        $factory = $this->createFormFactory();

        $car = new CarData();
        $form = $factory->create(CarData::class, $car);

        $this->assertSame(['name', 'seats'], array_keys($form->all()));

        $form->submit(['name' => 'Corvette', 'seats' => '2']);

        $this->assertSame('Corvette', $car->name);
        $this->assertSame(2, $car->seats);

        $blockPrefixes = $form->createView()->vars['block_prefixes'];
        $this->assertContains('car_data', $blockPrefixes);
        $this->assertContains('vehicle_data', $blockPrefixes);
    }

    public function testTypeExtensionsApplyToDataClassTypes()
    {
        $factory = $this->createFormFactory(static function (ContainerBuilder $container) {
            $container->register(UserDataExtension::class, UserDataExtension::class)->addTag('form.type_extension');
        });

        $form = $factory->create(UserData::class);

        $this->assertTrue($form->has('extra'));
        $this->assertFalse($form->get('extra')->getConfig()->getMapped());
    }

    public function testLiteralPercentSignsInOptions()
    {
        $factory = $this->createFormFactory(static function (ContainerBuilder $container) {
            $container->register(PercentData::class, PercentData::class)->addResourceTag('form.data_class');
        });

        $form = $factory->create(PercentData::class);

        $this->assertSame('%root%', $form->getConfig()->getOption('label'));
        $this->assertSame('100% of %value%', $form->get('rate')->getConfig()->getOption('label'));
    }

    public function testServiceReferenceInOptions()
    {
        $factory = $this->createFormFactory(static function (ContainerBuilder $container) {
            $container->register('color_labeler', ColorLabeler::class);
            $container->register(ReferenceData::class, ReferenceData::class)->addResourceTag('form.data_class');
        });

        $form = $factory->create(ReferenceData::class);
        $choices = $form->get('color')->createView()->vars['choices'];

        $this->assertSame(['RED', 'BLUE'], array_map(static fn ($choice) => $choice->label, $choices));
    }

    public function testTypeExtensionCanTargetADataClass()
    {
        $factory = $this->createFormFactory(static function (ContainerBuilder $container) {
            $container->register(CompanionExtension::class, CompanionExtension::class)->addTag('form.type_extension');
            $container->register(CompanionData::class, CompanionData::class)->addResourceTag('form.data_class');
        });

        $form = $factory->create(CompanionData::class);

        $this->assertTrue($form->has('companion'));
        $this->assertFalse($form->get('companion')->getConfig()->getMapped());
    }

    public function testUndiscoveredDataClassGetsADedicatedError()
    {
        $factory = $this->createFormFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not load type "Symfony\\Component\\Form\\Tests\\DependencyInjection\\ReferenceData": the class uses the #[AsFormType] attribute but is not registered as a form type. Make sure the class is discovered by the service container.');

        $factory->create(ReferenceData::class);
    }

    private function createFormFactory(?callable $configurator = null): FormFactoryInterface
    {
        $container = new ContainerBuilder();
        $container->register('form.extension', DependencyInjectionExtension::class)
            ->setPublic(true)
            ->setArguments([null, [], new IteratorArgument([])]);
        $container->addCompilerPass(new FormPass());

        $container->register(UserData::class, UserData::class)->addResourceTag('form.data_class');
        $container->register(AdminData::class, AdminData::class)->addResourceTag('form.data_class');
        $container->register('.abstract.'.VehicleData::class, VehicleData::class)->setAbstract(true)->addResourceTag('form.data_class');
        $container->register(CarData::class, CarData::class)->addResourceTag('form.data_class');

        if ($configurator) {
            $configurator($container);
        }

        $container->compile();

        return Forms::createFormFactoryBuilder()
            ->addExtension($container->get('form.extension'))
            ->getFormFactory();
    }
}

class UserDataExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield UserData::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('extra', null, ['mapped' => false]);
    }
}

#[AsFormType(options: ['label' => '%root%'])]
class PercentData
{
    #[FormField(options: ['label' => '100% of %value%'])]
    public ?string $rate = null;
}

class ColorLabeler
{
    public function label(string $choice): string
    {
        return strtoupper($choice);
    }
}

#[AsFormType]
class ReferenceData
{
    #[FormField(ChoiceType::class, [
        'choices' => ['red', 'blue'],
        'choice_label' => [new Reference('color_labeler'), 'label'],
    ])]
    public ?string $color = null;
}

class CompanionExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [CompanionData::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('companion', null, ['mapped' => false]);
    }
}

#[AsFormType]
class CompanionData
{
    #[FormField]
    public ?string $name = null;
}
