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
     * @return FormTypeInterface The type
     *
     * @throws Exception\InvalidArgumentException if the given type is not supported by this extension
     */
    public function getType(string $name);

    /**
     * Returns whether the given type is supported.
     *
     * @param string $name The name of the type
     *
     * @return bool Whether the type is supported by this extension
     */
    public function hasType(string $name);

    /**
     * Returns the extensions for the given type.
     *
     * @param string $name The name of the type
     *
     * @return FormTypeExtensionInterface[] An array of extensions as FormTypeExtensionInterface instances
     */
    public function getTypeExtensions(string $name);

    /**
     * Returns whether this extension provides type extensions for the given type.
     *
     * @param string $name The name of the type
     *
     * @return bool Whether the given type has extensions
     */
    public function hasTypeExtensions(string $name);

    /**
     * Returns the type guesser provided by this extension.
     *
     * @return FormTypeGuesserInterface|null The type guesser
     */
    public function getTypeGuesser();
}
