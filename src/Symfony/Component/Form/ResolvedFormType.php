<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormType implements ResolvedFormTypeInterface
{
    private FormTypeInterface $innerType;

    /**
     * @var FormTypeExtensionInterface[]
     */
    private array $typeExtensions;

    private ?ResolvedFormTypeInterface $parent;

    private OptionsResolver $optionsResolver;

    /**
     * @param FormTypeExtensionInterface[] $typeExtensions
     */
    public function __construct(FormTypeInterface $innerType, array $typeExtensions = [], ?ResolvedFormTypeInterface $parent = null)
    {
        foreach ($typeExtensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, FormTypeExtensionInterface::class);
            }
        }

        $this->innerType = $innerType;
        $this->typeExtensions = $typeExtensions;
        $this->parent = $parent;
    }

    public function getBlockPrefix(): string
    {
        return $this->innerType->getBlockPrefix();
    }

    public function getParent(): ?ResolvedFormTypeInterface
    {
        return $this->parent;
    }

    public function getInnerType(): FormTypeInterface
    {
        return $this->innerType;
    }

    public function getTypeExtensions(): array
    {
        return $this->typeExtensions;
    }

    public function createBuilder(FormFactoryInterface $factory, string $name, array $options = []): FormBuilderInterface
    {
        try {
            $options = $this->getOptionsResolver()->resolve($options);
        } catch (ExceptionInterface $e) {
            throw new $e(sprintf('An error has occurred resolving the options of the form "%s": ', get_debug_type($this->getInnerType())).$e->getMessage(), $e->getCode(), $e);
        }

        // Should be decoupled from the specific option at some point
        $dataClass = $options['data_class'] ?? null;

        $builder = $this->newBuilder($name, $dataClass, $factory, $options);
        $builder->setType($this);

        return $builder;
    }

    public function createView(FormInterface $form, ?FormView $parent = null): FormView
    {
        return $this->newView($parent);
    }

    /**
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->parent?->buildForm($builder, $options);

        $this->innerType->buildForm($builder, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildForm($builder, $options);
        }
    }

    /**
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $this->parent?->buildView($view, $form, $options);

        $this->innerType->buildView($view, $form, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildView($view, $form, $options);
        }
    }

    /**
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $this->parent?->finishView($view, $form, $options);

        $this->innerType->finishView($view, $form, $options);

        foreach ($this->typeExtensions as $extension) {
            /* @var FormTypeExtensionInterface $extension */
            $extension->finishView($view, $form, $options);
        }
    }

    public function getOptionsResolver(): OptionsResolver
    {
        if (!isset($this->optionsResolver)) {
            if (null !== $this->parent) {
                $this->optionsResolver = clone $this->parent->getOptionsResolver();
            } else {
                $this->optionsResolver = new OptionsResolver();
            }

            $this->innerType->configureOptions($this->optionsResolver);

            foreach ($this->typeExtensions as $extension) {
                $extension->configureOptions($this->optionsResolver);
            }
        }

        return $this->optionsResolver;
    }

    /**
     * Creates a new builder instance.
     *
     * Override this method if you want to customize the builder class.
     */
    protected function newBuilder(string $name, ?string $dataClass, FormFactoryInterface $factory, array $options): FormBuilderInterface
    {
        if ($this->innerType instanceof ButtonTypeInterface) {
            return new ButtonBuilder($name, $options);
        }

        if ($this->innerType instanceof SubmitButtonTypeInterface) {
            return new SubmitButtonBuilder($name, $options);
        }

        return new FormBuilder($name, $dataClass, new EventDispatcher(), $factory, $options);
    }

    /**
     * Creates a new view instance.
     *
     * Override this method if you want to customize the view class.
     */
    protected function newView(?FormView $parent = null): FormView
    {
        return new FormView($parent);
    }
}
