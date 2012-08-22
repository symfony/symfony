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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\FormException;

/**
 * The central registry of the Form component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormRegistry implements FormRegistryInterface
{
    /**
     * Extensions
     * @var array An array of FormExtensionInterface
     */
    private $extensions = array();

    /**
     * @var array
     */
    private $types = array();

    /**
     * @var FormTypeGuesserInterface
     */
    private $guesser;

    /**
     * @var ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    /**
     * Constructor.
     *
     * @param array                            $extensions          An array of FormExtensionInterface
     * @param ResolvedFormTypeFactoryInterface $resolvedTypeFactory The factory for resolved form types.
     *
     * @throws UnexpectedTypeException if any extension does not implement FormExtensionInterface
     */
    public function __construct(array $extensions, ResolvedFormTypeFactoryInterface $resolvedTypeFactory)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormExtensionInterface');
            }
        }

        $this->extensions = $extensions;
        $this->resolvedTypeFactory = $resolvedTypeFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function addType(ResolvedFormTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getType($name)
    {
        if (!is_string($name)) {
            throw new UnexpectedTypeException($name, 'string');
        }

        if (!isset($this->types[$name])) {
            /** @var FormTypeInterface $type */
            $type = null;

            foreach ($this->extensions as $extension) {
                /* @var FormExtensionInterface $extension */
                if ($extension->hasType($name)) {
                    $type = $extension->getType($name);
                    break;
                }
            }

            if (!$type) {
                throw new FormException(sprintf('Could not load type "%s"', $name));
            }

            $this->resolveAndAddType($type);
        }

        return $this->types[$name];
    }

    /**
     * Wraps a type into a ResolvedFormTypeInterface implementation and connects
     * it with its parent type.
     *
     * @param FormTypeInterface $type The type to resolve.
     *
     * @return ResolvedFormTypeInterface The resolved type.
     */
    private function resolveAndAddType(FormTypeInterface $type)
    {
        $parentType = $type->getParent();

        if ($parentType instanceof FormTypeInterface) {
            $this->resolveAndAddType($parentType);
            $parentType = $parentType->getName();
        }

        $typeExtensions = array();

        foreach ($this->extensions as $extension) {
            /* @var FormExtensionInterface $extension */
            $typeExtensions = array_merge(
                $typeExtensions,
                $extension->getTypeExtensions($type->getName())
            );
        }

        $this->addType($this->resolvedTypeFactory->createResolvedType(
            $type,
            $typeExtensions,
            $parentType ? $this->getType($parentType) : null
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function hasType($name)
    {
        if (isset($this->types[$name])) {
            return true;
        }

        try {
            $this->getType($name);
        } catch (FormException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser()
    {
        if (null === $this->guesser) {
            $guessers = array();

            foreach ($this->extensions as $extension) {
                /* @var FormExtensionInterface $extension */
                $guesser = $extension->getTypeGuesser();

                if ($guesser) {
                    $guessers[] = $guesser;
                }
            }

            $this->guesser = new FormTypeGuesserChain($guessers);
        }

        return $this->guesser;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
