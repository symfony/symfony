<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormInterface;

/**
 * Thrown when the constructor of a data class cannot be called because some of its arguments have no value.
 *
 * @internal
 */
final class MissingConstructorArgumentsException extends TransformationFailedException
{
    /**
     * @param class-string                 $class
     * @param array<string, FormInterface> $emptyForms The synchronized fields whose data is empty, indexed by the property they are mapped to
     */
    public function __construct(
        string $message,
        public readonly string $class,
        public readonly array $emptyForms,
    ) {
        parent::__construct($message);
    }
}
