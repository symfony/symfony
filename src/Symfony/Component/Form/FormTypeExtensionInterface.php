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

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @template T
 */
interface FormTypeExtensionInterface
{
    /**
     * Builds the form.
     *
     * This method is called after the extended type has built the form to
     * further modify it.
     *
     * @see FormTypeInterface::buildForm()
     *
     * @param FormBuilderInterface<T> $builder
     */
    public function buildForm(FormBuilderInterface $builder, array $options);

    /**
     * Builds the view.
     *
     * This method is called after the extended type has built the view to
     * further modify it.
     *
     * @see FormTypeInterface::buildView()
     *
     * @param FormView<T>      $view
     * @param FormInterface<T> $form
     */
    public function buildView(FormView $view, FormInterface $form, array $options);

    /**
     * Finishes the view.
     *
     * This method is called after the extended type has finished the view to
     * further modify it.
     *
     * @see FormTypeInterface::finishView()
     *
     * @param FormView<T>      $view
     * @param FormInterface<T> $form
     */
    public function finishView(FormView $view, FormInterface $form, array $options);

    public function configureOptions(OptionsResolver $resolver);

    /**
     * Gets the extended types.
     *
     * @return string[]
     */
    public static function getExtendedTypes(): iterable;
}
