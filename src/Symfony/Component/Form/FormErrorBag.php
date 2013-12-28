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

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * A bag of Form Errors.
 *
 * @author Wouter J <wouter@wouterj.nl>
 *
 * @since v2.5
 */
class FormErrorBag implements \RecursiveIterator, \Countable, \ArrayAccess
{
    /**
     * @var array An array of FormError and FormErrorBag instances
     */
    protected $errors = array();

    private $formName;

    public function setFormName($name)
    {
        $this->formName = $name;
    }

    /**
     * Adds a new form error.
     *
     * @param FormError $error
     */
    public function addError(FormError $error)
    {
        $this->errors[] = $error;
    }

    /**
     * Adds a new form error collection.
     *
     * @param string       $formName
     * @param FormErrorBag $collection
     */
    public function addCollection($formName, $collection)
    {
        $collection->setFormName($formName);

        $this->errors[$formName] = $collection;
    }

    public function current()
    {
        $current = current($this->errors);

        if (!$current instanceof FormError) {
            $this->next();

            if ($this->valid()) {
                $current = $this->current();
            }
        }

        return $current;
    }

    public function key()
    {
        return isset($this->formName) ? $this->formName : key($this->errors);
    }

    public function next()
    {
        return next($this->errors);
    }

    public function rewind()
    {
        reset($this->errors);
    }

    public function valid()
    {
        return null !== key($this->errors);
    }

    /**
     * {@inheritDoc}
     */
    public function hasChildren()
    {
        return current($this->errors) instanceof FormErrorBag;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren()
    {
        return current($this->errors);
    }

    public function count()
    {
        $count = 0;

        foreach ($this->errors as $error) {
            if ($error instanceof FormError) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Counts all errors, including errors from children.
     *
     * @return int
     */
    public function countAll()
    {
        $count = 0;

        foreach ($this->errors as $error) {
            if ($error instanceof FormErrorBag) {
                $count += $error->countAll();
            } else {
                $count++;
            }
        }

        return $count;
    }

    public function get($offset)
    {
        return $this->errors[$offset];
    }

    public function set($offset, $value)
    {
        $this->errors[$offset] = $value;
    }

    public function has($offset)
    {
        return isset($this->errors[$offset]);
    }

    public function all()
    {
        return $this->errors;
    }

    public function clear()
    {
        $this->replace();
    }

    public function remove($offset)
    {
        unset($this->errors[$offset]);
    }

    public function replace(array $errors = array())
    {
        $this->errors = $errors;
    }

    public function isEmpty()
    {
        return empty($this->errors);
    }

    public function keys()
    {
        return array_keys($this->errors);
    }

    public function __toString()
    {
        $level = func_num_args() > 0 ? func_get_arg(0) : 0;
        $errors = '';

        foreach ($this->errors as $key => $error) {
            if ($error instanceof self) {
                $errors .= str_repeat(' ', $level).$key.":\n";
                if ($err = $error->__toString($level + 4)) {
                    $errors .= $err;
                } else {
                    $errors .= str_repeat(' ', $level + 4)."No errors\n";
                }
            } else {
                $errors .= str_repeat(' ', $level).'ERROR: '.$error->getMessage()."\n";
            }
        }

        return $errors;
    }

    public function offsetExists($offset)
    {
        return $this->has($offset) && $this->errors[$offset] instanceof FormError;
    }

    public function offsetGet($offset)
    {
        $error = $this->get($offset);

        if ($error instanceof FormError) {
            return $error;
        }
    }

    public function offsetSet($offset, $value)
    {
        return $this->set($offset);
    }

    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }
}
