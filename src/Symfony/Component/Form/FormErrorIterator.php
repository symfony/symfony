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
 * @template T of FormError|FormErrorIterator
 *
 * @implements \ArrayAccess<int, T>
 * @implements \RecursiveIterator<int, T>
 * @implements \SeekableIterator<int, T>
 */
class FormErrorIterator implements \RecursiveIterator, \SeekableIterator, \ArrayAccess, \Countable, \Stringable
{
    /**
     * The prefix used for indenting nested error messages.
     */
    public const INDENTATION = '    ';

    /**
     * @var list<T>
     */
    private array $errors;

    /**
     * @param list<T> $errors
     *
     * @throws InvalidArgumentException If the errors are invalid
     */
    public function __construct(
        private FormInterface $form,
        array $errors,
    ) {
        foreach ($errors as $error) {
            if (!($error instanceof FormError || $error instanceof self)) {
                throw new InvalidArgumentException(sprintf('The errors must be instances of "Symfony\Component\Form\FormError" or "%s". Got: "%s".', __CLASS__, get_debug_type($error)));
            }
        }

        $this->errors = $errors;
    }

    /**
     * Returns all iterated error messages as string.
     */
    public function __toString(): string
    {
        $string = '';

        foreach ($this->errors as $error) {
            if ($error instanceof FormError) {
                $string .= 'ERROR: '.$error->getMessage()."\n";
            } else {
                /* @var self $error */
                $string .= $error->getForm()->getName().":\n";
                $string .= self::indent((string) $error);
            }
        }

        return $string;
    }

    /**
     * Returns the iterated form.
     */
    public function getForm(): FormInterface
    {
        return $this->form;
    }

    /**
     * Returns the current element of the iterator.
     *
     * @return T An error or an iterator containing nested errors
     */
    public function current(): FormError|self
    {
        return current($this->errors);
    }

    /**
     * Advances the iterator to the next position.
     */
    public function next(): void
    {
        next($this->errors);
    }

    /**
     * Returns the current position of the iterator.
     */
    public function key(): int
    {
        return key($this->errors);
    }

    /**
     * Returns whether the iterator's position is valid.
     */
    public function valid(): bool
    {
        return null !== key($this->errors);
    }

    /**
     * Sets the iterator's position to the beginning.
     *
     * This method detects if errors have been added to the form since the
     * construction of the iterator.
     */
    public function rewind(): void
    {
        reset($this->errors);
    }

    /**
     * Returns whether a position exists in the iterator.
     *
     * @param int $position The position
     */
    public function offsetExists(mixed $position): bool
    {
        return isset($this->errors[$position]);
    }

    /**
     * Returns the element at a position in the iterator.
     *
     * @param int $position The position
     *
     * @return T
     *
     * @throws OutOfBoundsException If the given position does not exist
     */
    public function offsetGet(mixed $position): FormError|self
    {
        if (!isset($this->errors[$position])) {
            throw new OutOfBoundsException('The offset '.$position.' does not exist.');
        }

        return $this->errors[$position];
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function offsetSet(mixed $position, mixed $value): void
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset(mixed $position): void
    {
        throw new BadMethodCallException('The iterator doesn\'t support modification of elements.');
    }

    /**
     * Returns whether the current element of the iterator can be recursed
     * into.
     */
    public function hasChildren(): bool
    {
        return current($this->errors) instanceof self;
    }

    public function getChildren(): self
    {
        if (!$this->hasChildren()) {
            throw new LogicException(sprintf('The current element is not iterable. Use "%s" to get the current element.', self::class.'::current()'));
        }

        /** @var self $children */
        $children = current($this->errors);

        return $children;
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
     */
    public function count(): int
    {
        return \count($this->errors);
    }

    /**
     * Sets the position of the iterator.
     *
     * @throws OutOfBoundsException If the position is invalid
     */
    public function seek(int $position): void
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
     */
    public function findByCodes(string|array $codes): static
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
