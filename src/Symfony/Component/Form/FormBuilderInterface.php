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
 *
 * @extends \Traversable<string, FormBuilderInterface>
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
     * @param string|FormBuilderInterface $child
     * @param array<string, mixed>        $options
     *
     * @return static
     */
    public function add($child, string $type = null, array $options = []);

    /**
     * Creates a form builder.
     *
     * @param string               $name    The name of the form or the name of the property
     * @param string|null          $type    The type of the form or null if name is a property
     * @param array<string, mixed> $options
     *
     * @return self
     */
    public function create(string $name, string $type = null, array $options = []);

    /**
     * Returns a child by name.
     *
     * @return self
     *
     * @throws Exception\InvalidArgumentException if the given child does not exist
     */
    public function get(string $name);

    /**
     * Removes the field with the given name.
     *
     * @return static
     */
    public function remove(string $name);

    /**
     * Returns whether a field with the given name exists.
     *
     * @return bool
     */
    public function has(string $name);

    /**
     * Returns the children.
     *
     * @return array<string, self>
     */
    public function all();

    /**
     * Creates the form.
     *
     * @return FormInterface
     */
    public function getForm();
}
