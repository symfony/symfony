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
 * The default implementation of FormFactoryBuilderInterface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryBuilder implements FormFactoryBuilderInterface
{
    /**
     * @var ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    /**
     * @var array
     */
    private $extensions = array();

    /**
     * @var array
     */
    private $types = array();

    /**
     * @var array
     */
    private $typeExtensions = array();

    /**
     * @var array
     */
    private $typeGuessers = array();

    /**
     * {@inheritdoc}
     */
    public function setResolvedTypeFactory(ResolvedFormTypeFactoryInterface $resolvedTypeFactory)
    {
        $this->resolvedTypeFactory = $resolvedTypeFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addExtension(FormExtensionInterface $extension)
    {
        $this->extensions[] = $extension;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addExtensions(array $extensions)
    {
        $this->extensions = array_merge($this->extensions, $extensions);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addType(FormTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypes(array $types)
    {
        foreach ($types as $type) {
            $this->types[$type->getName()] = $type;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeExtension(FormTypeExtensionInterface $typeExtension)
    {
        $this->typeExtensions[$typeExtension->getExtendedType()][] = $typeExtension;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeExtensions(array $typeExtensions)
    {
        foreach ($typeExtensions as $typeExtension) {
            $this->typeExtensions[$typeExtension->getExtendedType()][] = $typeExtension;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeGuesser(FormTypeGuesserInterface $typeGuesser)
    {
        $this->typeGuessers[] = $typeGuesser;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeGuessers(array $typeGuessers)
    {
        $this->typeGuessers = array_merge($this->typeGuessers, $typeGuessers);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormFactory()
    {
        $extensions = $this->extensions;

        if (count($this->types) > 0 || count($this->typeExtensions) > 0 || count($this->typeGuessers) > 0) {
            $typeGuesser = count($this->typeGuessers) > 1
                ? new FormTypeGuesserChain($this->typeGuessers)
                : $this->typeGuessers[0];

            $extensions[] = new PreloadedExtension($this->types, $this->typeExtensions, $typeGuesser);
        }

        $resolvedTypeFactory = $this->resolvedTypeFactory ?: new ResolvedFormTypeFactory();
        $registry = new FormRegistry($extensions, $resolvedTypeFactory);

        return new FormFactory($registry, $resolvedTypeFactory);
    }
}
