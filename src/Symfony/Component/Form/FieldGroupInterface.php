<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A field group bundling multiple form fields
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FieldGroupInterface extends FieldInterface, \ArrayAccess, \Traversable, \Countable
{
    /**
     * Returns whether this field group is virtual
     *
     * Virtual field groups are skipped when mapping property paths of a form
     * tree to an object.
     *
     * Example:
     *
     * <code>
     * $group = new FieldGroup('address');
     * $group->add(new TextField('street'));
     * $group->add(new TextField('postal_code'));
     * $form->add($group);
     * </code>
     *
     * If $group is non-virtual, the fields "street" and "postal_code"
     * are mapped to the property paths "address.street" and
     * "address.postal_code". If $group is virtual though, the fields are
     * mapped directly to "street" and "postal_code".
     *
     * @return boolean  Whether the group is virtual
     */
    public function isVirtual();
}