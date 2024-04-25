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
     * @param array<string, mixed> $options
     */
    public function add(string|self $child, ?string $type = null, array $options = []): static;

    /**
     * Creates a form builder.
     *
     * @param string               $name    The name of the form or the name of the property
     * @param string|null          $type    The type of the form or null if name is a property
     * @param array<string, mixed> $options
     */
    public function create(string $name, ?string $type = null, array $options = []): self;

    /**
     * Returns a child by name.
     *
     * @throws Exception\InvalidArgumentException if the given child does not exist
     */
    public function get(string $name): self;

    /**
     * Removes the field with the given name.
     */
    public function remove(string $name): static;

    /**
     * Returns whether a field with the given name exists.
     */
    public function has(string $name): bool;

    /**
     * Returns the children.
     *
     * @return array<string, self>
     */
    public function all(): array;

    /**
     * Creates the form.
     */
    public function getForm(): FormInterface;
}
