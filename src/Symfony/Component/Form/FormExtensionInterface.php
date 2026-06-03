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
 * Interface for extensions which provide types, type extensions and a guesser.
 */
interface FormExtensionInterface
{
    /**
     * Returns a type by name.
     *
     * @param string $name The name of the type
     *
     * @throws Exception\InvalidArgumentException if the given type is not supported by this extension
     */
    public function getType(string $name): FormTypeInterface;

    /**
     * Returns whether the given type is supported.
     *
     * @param string $name The name of the type
     */
    public function hasType(string $name): bool;

    /**
     * Returns the extensions for the given type.
     *
     * @param string $name The name of the type
     *
     * @return FormTypeExtensionInterface[]
     */
    public function getTypeExtensions(string $name): array;

    /**
     * Returns whether this extension provides type extensions for the given type.
     *
     * @param string $name The name of the type
     */
    public function hasTypeExtensions(string $name): bool;

    /**
     * Returns the type guesser provided by this extension.
     */
    public function getTypeGuesser(): ?FormTypeGuesserInterface;
}
