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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\Tests\Fixtures\ConfigurableFormType;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormTypeTest extends TestCase
{
    private $calls;

    /**
     * @var FormTypeInterface
     */
    private $parentType;

    /**
     * @var FormTypeInterface
     */
    private $type;

    /**
     * @var FormTypeExtensionInterface
     */
    private $extension1;

    /**
     * @var FormTypeExtensionInterface
     */
    private $extension2;

    /**
     * @var ResolvedFormType
     */
    private $parentResolvedType;

    /**
     * @var ResolvedFormType
     */
    private $resolvedType;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    protected function setUp(): void
    {
        $this->calls = [];
        $this->parentType = new UsageTrackingParentFormType($this->calls);
        $this->type = new UsageTrackingFormType($this->calls);
        $this->extension1 = new UsageTrackingFormTypeExtension($this->calls, ['c' => 'c_default']);
        $this->extension2 = new UsageTrackingFormTypeExtension($this->calls, ['d' => 'd_default']);
        $this->parentResolvedType = new ResolvedFormType($this->parentType);
        $this->resolvedType = new ResolvedFormType($this->type, [$this->extension1, $this->extension2], $this->parentResolvedType);
        $this->formFactory = new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory()));
    }

    public function testGetOptionsResolver()
    {
        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];
        $resolvedOptions = ['a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default', 'foo' => 'bar'];

        $resolver = $this->resolvedType->getOptionsResolver();

        $this->assertEquals($resolvedOptions, $resolver->resolve($givenOptions));
    }

    public function testCreateBuilder()
    {
        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];
        $resolvedOptions = ['b' => 'b_default', 'd' => 'd_default', 'a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];

        $builder = $this->resolvedType->createBuilder($this->formFactory, 'name', $givenOptions);

        $this->assertSame($this->resolvedType, $builder->getType());
        $this->assertSame($resolvedOptions, $builder->getOptions());
        $this->assertNull($builder->getDataClass());
    }

    public function testCreateBuilderWithDataClassOption()
    {
        $resolvedOptions = [
            'a' => 'a_default',
            'b' => 'b_default',
            'c' => 'c_default',
            'd' => 'd_default',
            'data_class' => \stdClass::class,
            'foo' => 'bar',
        ];

        $builder = $this->resolvedType->createBuilder($this->formFactory, 'name', [
            'data_class' => \stdClass::class,
            'foo' => 'bar',
        ]);

        $this->assertSame($this->resolvedType, $builder->getType());
        $this->assertSame($resolvedOptions, $builder->getOptions());
        $this->assertSame(\stdClass::class, $builder->getDataClass());
    }

    public function testFailsCreateBuilderOnInvalidFormOptionsResolution()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(sprintf('An error has occurred resolving the options of the form "%s": The required option "foo" is missing.', UsageTrackingFormType::class));

        $this->resolvedType->createBuilder($this->formFactory, 'name');
    }

    public function testBuildForm()
    {
        $this->resolvedType->buildForm(new FormBuilder(null, null, new EventDispatcher(), $this->formFactory), []);

        $this->assertSame([$this->parentType, $this->type, $this->extension1, $this->extension2], $this->calls['buildForm']);
    }

    public function testCreateView()
    {
        $view = $this->resolvedType->createView($this->formFactory->create());

        $this->assertInstanceOf(FormView::class, $view);
        $this->assertNull($view->parent);
    }

    public function testCreateViewWithParent()
    {
        $parentView = new FormView();

        $view = $this->resolvedType->createView($this->formFactory->create(), $parentView);

        $this->assertInstanceOf(FormView::class, $view);
        $this->assertSame($parentView, $view->parent);
    }

    public function testBuildView()
    {
        $this->resolvedType->buildView(new FormView(), $this->formFactory->create(), []);

        $this->assertSame([$this->parentType, $this->type, $this->extension1, $this->extension2], $this->calls['buildView']);
    }

    public function testFinishView()
    {
        $this->resolvedType->finishView(new FormView(), $this->formFactory->create(), []);

        $this->assertSame([$this->parentType, $this->type, $this->extension1, $this->extension2], $this->calls['finishView']);
    }

    public function testGetBlockPrefix()
    {
        $resolvedType = new ResolvedFormType(new ConfigurableFormType());

        $this->assertSame('configurable_form_prefix', $resolvedType->getBlockPrefix());
    }

    /**
     * @dataProvider provideTypeClassBlockPrefixTuples
     */
    public function testBlockPrefixDefaultsToFQCNIfNoName($typeClass, $blockPrefix)
    {
        $resolvedType = new ResolvedFormType(new $typeClass());

        $this->assertSame($blockPrefix, $resolvedType->getBlockPrefix());
    }

    public static function provideTypeClassBlockPrefixTuples()
    {
        return [
            [Fixtures\FooType::class, 'foo'],
            [Fixtures\Foo::class, 'foo'],
            [Fixtures\Type::class, 'type'],
            [Fixtures\FooBarHTMLType::class, 'foo_bar_html'],
            [__NAMESPACE__.'\Fixtures\Foo1Bar2Type', 'foo1_bar2'],
            [Fixtures\FBooType::class, 'f_boo'],
        ];
    }
}

class UsageTrackingFormType extends AbstractType
{
    use UsageTrackingTrait;

    public function __construct(array &$calls)
    {
        $this->calls = &$calls;
    }

    public function getParent(): string
    {
        return UsageTrackingParentFormType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('b', 'b_default');
        $resolver->setDefined('data_class');
        $resolver->setRequired('foo');
    }
}

class UsageTrackingParentFormType extends AbstractType
{
    use UsageTrackingTrait;

    public function __construct(array &$calls)
    {
        $this->calls = &$calls;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('a', 'a_default');
    }
}

class UsageTrackingFormTypeExtension extends AbstractTypeExtension
{
    use UsageTrackingTrait;

    private $defaultOptions;

    public function __construct(array &$calls, array $defaultOptions)
    {
        $this->calls = &$calls;
        $this->defaultOptions = $defaultOptions;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults($this->defaultOptions);
    }

    public static function getExtendedTypes(): iterable
    {
        yield FormType::class;
    }
}

trait UsageTrackingTrait
{
    private $calls;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->calls['buildForm'][] = $this;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $this->calls['buildView'][] = $this;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->calls['finishView'][] = $this;
    }
}
