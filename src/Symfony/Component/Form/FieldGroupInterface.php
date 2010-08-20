<?php

namespace Symfony\Component\Form;

/**
 * A field group bundling multiple form fields
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FieldGroupInterface extends FieldInterface, \ArrayAccess, \Traversable, \Countable
{
}