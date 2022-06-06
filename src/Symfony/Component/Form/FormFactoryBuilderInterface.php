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
 * A builder for FormFactoryInterface objects.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormFactoryBuilderInterface
{
    /**
     * Sets the factory for creating ResolvedFormTypeInterface instances.
     *
     * @return $this
     */
    public function setResolvedTypeFactory(ResolvedFormTypeFactoryInterface $resolvedTypeFactory);

    /**
     * Adds an extension to be loaded by the factory.
     *
     * @return $this
     */
    public function addExtension(FormExtensionInterface $extension);

    /**
     * Adds a list of extensions to be loaded by the factory.
     *
     * @param FormExtensionInterface[] $extensions The extensions
     *
     * @return $this
     */
    public function addExtensions(array $extensions);

    /**
     * Adds a form type to the factory.
     *
     * @return $this
     */
    public function addType(FormTypeInterface $type);

    /**
     * Adds a list of form types to the factory.
     *
     * @param FormTypeInterface[] $types The form types
     *
     * @return $this
     */
    public function addTypes(array $types);

    /**
     * Adds a form type extension to the factory.
     *
     * @return $this
     */
    public function addTypeExtension(FormTypeExtensionInterface $typeExtension);

    /**
     * Adds a list of form type extensions to the factory.
     *
     * @param FormTypeExtensionInterface[] $typeExtensions The form type extensions
     *
     * @return $this
     */
    public function addTypeExtensions(array $typeExtensions);

    /**
     * Adds a type guesser to the factory.
     *
     * @return $this
     */
    public function addTypeGuesser(FormTypeGuesserInterface $typeGuesser);

    /**
     * Adds a list of type guessers to the factory.
     *
     * @param FormTypeGuesserInterface[] $typeGuessers The type guessers
     *
     * @return $this
     */
    public function addTypeGuessers(array $typeGuessers);

    /**
     * Builds and returns the factory.
     *
     * @return FormFactoryInterface
     */
    public function getFormFactory();
}
