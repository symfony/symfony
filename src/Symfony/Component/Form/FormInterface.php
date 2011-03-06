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
 * A field group bundling multiple form fields
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FormInterface extends FieldInterface, \ArrayAccess, \Traversable, \Countable
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
     * $group = new Form('address');
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
     * @return Boolean  Whether the group is virtual
     */
    public function isVirtual();
}