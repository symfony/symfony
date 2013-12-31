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

use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * Iterates over the errors of a form.
 *
 * Optionally, this class supports recursive iteration. In order to iterate
 * recursively, set the constructor argument $deep to true. Now each element
 * returned by the iterator is either an instance of {@link FormError} or of
 * {@link FormErrorIterator}, in case the errors belong to a sub-form.
 *
 * You can also wrap the iterator into a {@link \RecursiveIteratorIterator} to
 * flatten the recursive structure into a flat list of errors.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since 2.5
 */
class FormErrorIterator implements \RecursiveIterator, \SeekableIterator, \ArrayAccess, \Countable
{
    /**
     * The prefix used for indenting nested error messages.
     *
     * @var string
     */
    const INDENTATION = '    ';

    /**
     * @var FormInterface
     */
    private $form;

    /**
     * @var Boolean
     */
    private $deep;

    /**
     * @var Boolean
     */
    private $flatten;

    /**
     * @var array
     */
    private $elements;

    /**
     * Creates a new iterator.
     *
     * @param array         $errors  The iterated errors
     * @param FormInterface $form    The form the errors belong to
     * @param Boolean       $deep    Whether to include the errors of child
     *                               forms
     * @param Boolean       $flatten Whether to flatten the recursive list of
     *                               errors into a flat list
     */
    public function __construct(array &$errors, FormInterface $form, $deep = false, $flatten = false)
    {
        $this->errors = &$errors;
        $this->form = $form;
        $this->deep = $deep;
        $this->flatten = $flatten;

        $this->rewind();
    }

    /**
     * Returns all iterated error messages as string.
     *
     * @return string The iterated error messages
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->elements as $element) {
            if ($element instanceof FormError) {
                $string .= 'ERROR: '.$element->getMessage()."\n";
            } else {
                /** @var $element FormErrorIterator */
                $string .= $element->form->getName().":\n";
                $string .= self::indent((string) $element);
            }
        }

        return $string;
    }

    /**
     * Returns the iterated form.
     *
     * @return FormInterface The form whose errors are iterated by this object.
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Returns the current element of the iterator.
     *
     * @return FormError|FormErrorIterator An error or an iterator for nested
     *                                     errors.
     */
    public function current()
    {
        return current($this->elements);
    }

    /**
     * Advances the iterator to the next position.
     */
    public function next()
    {
        next($this->elements);
    }

    /**
     * Returns the current position of the iterator.
     *
     * @return integer The 0-indexed position.
     */
    public function key()
    {
        return key($this->elements);
    }

    /**
     * Returns whether the iterator's position is valid.
     *
     * @return Boolean Whether the iterator is valid.
     */
    public function valid()
    {
        return null !== key($this->elements);
    }

    /**
     * Sets the iterator's position to the beginning.
     *
     * This method detects if errors have been added to the form since the
     * construction of the iterator.
     */
    public function rewind()
    {
        $this->elements = $this->errors;

        if ($this->deep) {
            foreach ($this->form as $child) {
                /** @var FormInterface $child */
                if ($child->isSubmitted() && $child->isValid()) {
                    continue;
                }

                $iterator = $child->getErrors(true, $this->flatten);

                if (0 === count($iterator)) {
                    continue;
                }

                if ($this->flatten) {
                    foreach ($iterator as $error) {
                        $this->elements[] = $error;
                    }
                } else {
                    $this->elements[] = $iterator;
                }
            }
        }

        reset($this->elements);
    }

    /**
     * Returns whether a position exists in the iterator.
     *
     * @param integer $position The position
     *
     * @return Boolean Whether that position exists
     */
    public function offsetExists($position)
    {
        return isset($this->elements[$position]);
    }

    /**
     * Returns the element at a position in the iterator.
     *
     * @param integer $position The position
     *
     * @return FormError|FormErrorIterator The element at the given position
     *
     * @throws OutOfBoundsException If the given position does not exist
     */
    public function offsetGet($position)
    {
        if (!isset($this->elements[$position])) {
            throw new OutOfBoundsException('The offset '.$position.' does not exist.');
        }

        return $this->elements[$position];
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function offsetSet($position, $value)
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset($position)
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Returns whether the current element of the iterator can be recursed
     * into.
     *
     * @return Boolean Whether the current element is an instance of this class
     */
    public function hasChildren()
    {
        return current($this->elements) instanceof self;
    }

    /**
     * Alias of {@link current()}.
     */
    public function getChildren()
    {
        return current($this->elements);
    }

    /**
     * Returns the number of elements in the iterator.
     *
     * Note that this is not the total number of errors, if the constructor
     * parameter $deep was set to true! In that case, you should wrap the
     * iterator into a {@link \RecursiveIteratorIterator} with the standard mode
     * {@link \RecursiveIteratorIterator::LEAVES_ONLY} and count the result.
     *
     *     $iterator = new \RecursiveIteratorIterator($form->getErrors(true));
     *     $count = count(iterator_to_array($iterator));
     *
     * Alternatively, set the constructor argument $flatten to true as well.
     *
     *     $count = count($form->getErrors(true, true));
     *
     * @return integer The number of iterated elements
     */
    public function count()
    {
        return count($this->elements);
    }

    /**
     * Sets the position of the iterator.
     *
     * @param integer $position The new position
     *
     * @throws OutOfBoundsException If the position is invalid
     */
    public function seek($position)
    {
        if (!isset($this->elements[$position])) {
            throw new OutOfBoundsException('The offset '.$position.' does not exist.');
        }

        reset($this->elements);

        while ($position !== key($this->elements)) {
            next($this->elements);
        }
    }

    /**
     * Utility function for indenting multi-line strings.
     *
     * @param string $string The string
     *
     * @return string The indented string
     */
    private static function indent($string)
    {
        return rtrim(self::INDENTATION.str_replace("\n", "\n".self::INDENTATION, $string), ' ');
    }
}
