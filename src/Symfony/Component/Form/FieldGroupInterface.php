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
}