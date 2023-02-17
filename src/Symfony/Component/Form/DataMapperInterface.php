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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface DataMapperInterface
{
    /**
     * Maps the view data of a compound form to its children.
     *
     * The method is responsible for calling {@link FormInterface::setData()}
     * on the children of compound forms, defining their underlying model data.
     *
     * @param mixed                              $viewData View data of the compound form being initialized
     * @param \Traversable<mixed, FormInterface> $forms    A list of {@link FormInterface} instances
     *
     * @return void
     *
     * @throws Exception\UnexpectedTypeException if the type of the data parameter is not supported
     */
    public function mapDataToForms(mixed $viewData, \Traversable $forms);

    /**
     * Maps the model data of a list of children forms into the view data of their parent.
     *
     * This is the internal cascade call of FormInterface::submit for compound forms, since they
     * cannot be bound to any input nor the request as scalar, but their children may:
     *
     *     $compoundForm->submit($arrayOfChildrenViewData)
     *     // inside:
     *     $childForm->submit($childViewData);
     *     // for each entry, do the same and/or reverse transform
     *     $this->dataMapper->mapFormsToData($compoundForm, $compoundInitialViewData)
     *     // then reverse transform
     *
     * When a simple form is submitted the following is happening:
     *
     *     $simpleForm->submit($submittedViewData)
     *     // inside:
     *     $this->viewData = $submittedViewData
     *     // then reverse transform
     *
     * The model data can be an array or an object, so this second argument is always passed
     * by reference.
     *
     * @param \Traversable<mixed, FormInterface> $forms     A list of {@link FormInterface} instances
     * @param mixed                              &$viewData The compound form's view data that get mapped
     *                                                      its children model data
     *
     * @return void
     *
     * @throws Exception\UnexpectedTypeException if the type of the data parameter is not supported
     */
    public function mapFormsToData(\Traversable $forms, mixed &$viewData);
}
