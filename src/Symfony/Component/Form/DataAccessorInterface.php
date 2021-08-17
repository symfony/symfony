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
 * Writes and reads values to/from an object or array bound to a form.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface DataAccessorInterface
{
    /**
     * Returns the value at the end of the property of the object graph.
     *
     * @param object|array  $viewData The view data of the compound form
     * @param FormInterface $form     The {@link FormInterface()} instance to check
     *
     * @return mixed
     *
     * @throws Exception\AccessException If unable to read from the given form data
     */
    public function getValue($viewData, FormInterface $form);

    /**
     * Sets the value at the end of the property of the object graph.
     *
     * @param object|array  $viewData The view data of the compound form
     * @param mixed         $value    The value to set at the end of the object graph
     * @param FormInterface $form     The {@link FormInterface()} instance to check
     *
     * @throws Exception\AccessException If unable to write the given value
     */
    public function setValue(&$viewData, $value, FormInterface $form): void;

    /**
     * Returns whether a value can be read from an object graph.
     *
     * Whenever this method returns true, {@link getValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     *
     * @param object|array  $viewData The view data of the compound form
     * @param FormInterface $form     The {@link FormInterface()} instance to check
     *
     * @return bool
     */
    public function isReadable($viewData, FormInterface $form): bool;

    /**
     * Returns whether a value can be written at a given object graph.
     *
     * Whenever this method returns true, {@link setValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     *
     * @param object|array  $viewData The view data of the compound form
     * @param FormInterface $form     The {@link FormInterface()} instance to check
     *
     * @return bool
     */
    public function isWritable($viewData, FormInterface $form): bool;
}
