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

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Iterates over the errors of a form.
 *
 * This class supports recursive iteration. In order to iterate recursively,
 * pass a structure of {@link FormError} and {@link FormErrorIterator} objects
 * to the $errors constructor argument.
 *
 * You can also wrap the iterator into a {@link \RecursiveIteratorIterator} to
 * flatten the recursive structure into a flat list of errors.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \ArrayAccess<int, FormError|FormErrorIterator>
 * @implements \RecursiveIterator<int, FormError|FormErrorIterator>
 * @implements \SeekableIterator<int, FormError|FormErrorIterator>
 */
class FormErrorIterator implements \RecursiveIterator, \SeekableIterator, \ArrayAccess, \Countable
{
    /**
     * The prefix used for indenting nested error messages.
     */
    public const INDENTATION = '    ';

    private $form;
    private $errors;

    /**
     * @param list<FormError|self> $errors
     *
     * @throws InvalidArgumentException If the errors are invalid
     */
    public function __construct(FormInterface $form, array $errors)
    {
        foreach ($errors as $error) {
            if (!($error instanceof FormError || $error instanceof self)) {
                throw new InvalidArgumentException(sprintf('The errors must be instances of "Symfony\Component\Form\FormError" or "%s". Got: "%s".', __CLASS__, get_debug_type($error)));
            }
        }

        $this->form = $form;
        $this->errors = $errors;
    }

    /**
     * Returns all iterated error messages as string.
     *
     * @return string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->errors as $error) {
            if ($error instanceof FormError) {
                $string .= 'ERROR: '.$error->getMessage()."\n";
            } else {
                /* @var self $error */
                $string .= $error->form->getName().":\n";
                $string .= self::indent((string) $error);
            }
        }

        return $string;
    }

    /**
     * Returns the iterated form.
     *
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Returns the current element of the iterator.
     *
     * @return FormError|self An error or an iterator containing nested errors
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->errors);
    }

    /**
     * Advances the iterator to the next position.
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        next($this->errors);
    }

    /**
     * Returns the current position of the iterator.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->errors);
    }

    /**
     * Returns whether the iterator's position is valid.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return null !== key($this->errors);
    }

    /**
     * Sets the iterator's position to the beginning.
     *
     * This method detects if errors have been added to the form since the
     * construction of the iterator.
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        reset($this->errors);
    }

    /**
     * Returns whether a position exists in the iterator.
     *
     * @param int $position The position
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($position)
    {
        return isset($this->errors[$position]);
    }

    /**
     * Returns the element at a position in the iterator.
     *
     * @param int $position The position
     *
     * @return FormError|FormErrorIterator
     *
     * @throws OutOfBoundsException If the given position does not exist
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($position)
    {
        if (!isset($this->errors[$position])) {
            throw new OutOfBoundsException('The offset '.$position.' does not exist.');
        }

        return $this->errors[$position];
    }

    /**
     * Unsupported method.
     *
     * @return void
     *
     * @throws BadMethodCallException
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($position, $value)
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Unsupported method.
     *
     * @return void
     *
     * @throws BadMethodCallException
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($position)
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Returns whether the current element of the iterator can be recursed
     * into.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function hasChildren()
    {
        return current($this->errors) instanceof self;
    }

    /**
     * @return self
     */
    #[\ReturnTypeWillChange]
    public function getChildren()
    {
        if (!$this->hasChildren()) {
            trigger_deprecation('symfony/form', '5.4', 'Calling "%s()" if the current element is not iterable is deprecated, call "%s" to get the current element.', __METHOD__, self::class.'::current()');
            // throw new LogicException(sprintf('The current element is not iterable. Use "%s" to get the current element.', self::class.'::current()'));
        }

        return current($this->errors);
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
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->errors);
    }

    /**
     * Sets the position of the iterator.
     *
     * @param int $position The new position
     *
     * @return void
     *
     * @throws OutOfBoundsException If the position is invalid
     */
    #[\ReturnTypeWillChange]
    public function seek($position)
    {
        if (!isset($this->errors[$position])) {
            throw new OutOfBoundsException('The offset '.$position.' does not exist.');
        }

        reset($this->errors);

        while ($position !== key($this->errors)) {
            next($this->errors);
        }
    }

    /**
     * Creates iterator for errors with specific codes.
     *
     * @param string|string[] $codes The codes to find
     *
     * @return static
     */
    public function findByCodes($codes)
    {
        $codes = (array) $codes;
        $errors = [];
        foreach ($this as $error) {
            $cause = $error->getCause();
            if ($cause instanceof ConstraintViolation && \in_array($cause->getCode(), $codes, true)) {
                $errors[] = $error;
            }
        }

        return new static($this->form, $errors);
    }

    /**
     * Utility function for indenting multi-line strings.
     */
    private static function indent(string $string): string
    {
        return rtrim(self::INDENTATION.str_replace("\n", "\n".self::INDENTATION, $string), ' ');
    }
}
