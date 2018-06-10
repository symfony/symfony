<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Collects and structures information about forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormDataCollectorInterface extends DataCollectorInterface
{
    /**
     * Stores configuration data of the given form and its children.
     */
    public function collectConfiguration(FormInterface $form);

    /**
     * Stores the default data of the given form and its children.
     */
    public function collectDefaultData(FormInterface $form);

    /**
     * Stores the submitted data of the given form and its children.
     */
    public function collectSubmittedData(FormInterface $form);

    /**
     * Stores the view variables of the given form view and its children.
     */
    public function collectViewVariables(FormView $view);

    /**
     * Specifies that the given objects represent the same conceptual form.
     */
    public function associateFormWithView(FormInterface $form, FormView $view);

    /**
     * Assembles the data collected about the given form and its children as
     * a tree-like data structure.
     *
     * The result can be queried using {@link getData()}.
     */
    public function buildPreliminaryFormTree(FormInterface $form);

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
     */
    public function buildFinalFormTree(FormInterface $form, FormView $view);

    /**
     * Returns all collected data.
     *
     * @return array
     */
    public function getData();
}
