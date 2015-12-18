<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Profiler;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;

/**
 * Collects and structures information about forms.
 *
 * @since  2.8
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormDataCollectorInterface extends DataCollectorInterface, EventSubscriberInterface
{
    /**
     * Stores the view variables of the given form view and its children.
     *
     * @param FormView $view A root form view
     */
    public function collectViewVariables(FormView $view);

    /**
     * Specifies that the given objects represent the same conceptual form.
     *
     * @param FormInterface $form A form object
     * @param FormView      $view A view object
     */
    public function associateFormWithView(FormInterface $form, FormView $view);

    /**
     * Assembles the data collected about the given form and its children as
     * a tree-like data structure.
     *
     * The result can be queried using {@link getData()}.
     *
     * Contrary to {@link buildPreliminaryFormTree()}, a {@link FormView}
     * object has to be passed. The tree structure of this view object will be
     * used for structuring the resulting data. That means, if a child is
     * present in the view, but not in the form, it will be present in the final
     * data array anyway.
     *
     * When {@link FormView} instances are present in the view tree, for which
     * no corresponding {@link FormInterface} objects can be found in the form
     * tree, only the view data will be included in the result. If a
     * corresponding {@link FormInterface} exists otherwise, call
     * {@link associateFormWithView()} before calling this method.
     *
     * @param FormInterface $form A root form
     * @param FormView      $view A root view
     */
    public function buildFinalFormTree(FormInterface $form, FormView $view);
}
