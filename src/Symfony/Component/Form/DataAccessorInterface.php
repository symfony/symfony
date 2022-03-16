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
     * @throws Exception\AccessException If unable to read from the given form data
     */
    public function getValue(object|array $viewData, FormInterface $form): mixed;

    /**
     * Sets the value at the end of the property of the object graph.
     *
     * @throws Exception\AccessException If unable to write the given value
     */
    public function setValue(object|array &$viewData, mixed $value, FormInterface $form): void;

    /**
     * Returns whether a value can be read from an object graph.
     *
     * Whenever this method returns true, {@link getValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     */
    public function isReadable(object|array $viewData, FormInterface $form): bool;

    /**
     * Returns whether a value can be written at a given object graph.
     *
     * Whenever this method returns true, {@link setValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     */
    public function isWritable(object|array $viewData, FormInterface $form): bool;
}
