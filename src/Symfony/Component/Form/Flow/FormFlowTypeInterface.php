<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;

/**
 * A type that should be converted into a {@link FormFlow} instance.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface FormFlowTypeInterface extends FormTypeInterface
{
    /**
     * Builds the multistep form.
     *
     * This method is called for each multistep type. Type extensions can further
     * modify the multistep form.
     *
     * @param array<string, mixed> $options
     */
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void;

    /**
     * Builds the multistep form view.
     *
     * This method is called for each multistep type. Type extensions can further
     * modify the view.
     *
     * A view of a multistep form is built before the views of the child forms are built.
     * This means that you cannot access child views in this method. If you need
     * to do so, move your logic to {@link finishViewFlow()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function buildViewFlow(FormView $view, FormFlowInterface $form, array $options): void;

    /**
     * Finishes the multistep form view.
     *
     * This method gets called for each multistep type. Type extensions can further
     * modify the view.
     *
     * When this method is called, views of the multistep form's children have already
     * been built and finished and can be accessed. You should only implement
     * such logic in this method that actually accesses child views. For everything
     * else you are recommended to implement {@link buildViewFlow()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function finishViewFlow(FormView $view, FormFlowInterface $form, array $options): void;
}
