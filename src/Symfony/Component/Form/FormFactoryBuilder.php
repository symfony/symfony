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
     * @var FormExtensionInterface[]
     */
    private $extensions = array();

    /**
     * @var FormTypeInterface[]
     */
    private $types = array();

    /**
     * @var FormTypeExtensionInterface[]
     */
    private $typeExtensions = array();

    /**
     * @var FormTypeGuesserInterface[]
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
        $this->types[] = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypes(array $types)
    {
        foreach ($types as $type) {
            $this->types[] = $type;
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
            if (count($this->typeGuessers) > 1) {
                $typeGuesser = new FormTypeGuesserChain($this->typeGuessers);
            } else {
                $typeGuesser = isset($this->typeGuessers[0]) ? $this->typeGuessers[0] : null;
            }

            $extensions[] = new PreloadedExtension($this->types, $this->typeExtensions, $typeGuesser);
        }

        $resolvedTypeFactory = $this->resolvedTypeFactory ?: new ResolvedFormTypeFactory();
        $registry = new FormRegistry($extensions, $resolvedTypeFactory);

        return new FormFactory($registry, $resolvedTypeFactory);
    }
}
