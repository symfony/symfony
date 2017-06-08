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
interface FormBuilderInterface extends \Traversable, \Countable, FormConfigBuilderInterface
{
    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy.
     *
     * @param string|int|FormBuilderInterface $child
     * @param string|FormTypeInterface        $type
     * @param array                           $options
     *
     * @return self
     */
    public function add($child, $type = null, array $options = array());

    /**
     * Creates a form builder.
     *
     * @param string                   $name    The name of the form or the name of the property
     * @param string|FormTypeInterface $type    The type of the form or null if name is a property
     * @param array                    $options The options
     *
     * @return self
     */
    public function create($name, $type = null, array $options = array());

    /**
     * Returns a child by name.
     *
     * @param string $name The name of the child
     *
     * @return self
     *
     * @throws Exception\InvalidArgumentException if the given child does not exist
     */
    public function get($name);

    /**
     * Removes the field with the given name.
     *
     * @param string $name
     *
     * @return self
     */
    public function remove($name);

    /**
     * Returns whether a field with the given name exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name);

    /**
     * Returns the children.
     *
     * @return array
     */
    public function all();

    /**
     * Creates the form.
     *
     * @return FormInterface The form
     */
    public function getForm();
}
