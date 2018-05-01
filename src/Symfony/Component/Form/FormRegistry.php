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

use Symfony\Component\Form\Exception\ExceptionInterface;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * The central registry of the Form component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormRegistry implements FormRegistryInterface
{
    /**
     * Extensions.
     *
     * @var FormExtensionInterface[] An array of FormExtensionInterface
     */
    private $extensions = array();

    /**
     * @var ResolvedFormTypeInterface[]
     */
    private $types = array();

    /**
     * @var FormTypeGuesserInterface|false|null
     */
    private $guesser = false;

    /**
     * @var ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    private $checkedTypes = array();

    /**
     * @param FormExtensionInterface[]         $extensions          An array of FormExtensionInterface
     * @param ResolvedFormTypeFactoryInterface $resolvedTypeFactory The factory for resolved form types
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
    public function getType($name)
    {
        if (!isset($this->types[$name])) {
            $type = null;

            foreach ($this->extensions as $extension) {
                if ($extension->hasType($name)) {
                    $type = $extension->getType($name);
                    break;
                }
            }

            if (!$type) {
                // Support fully-qualified class names
                if (!class_exists($name)) {
                    throw new InvalidArgumentException(sprintf('Could not load type "%s": class does not exist.', $name));
                }
                if (!is_subclass_of($name, 'Symfony\Component\Form\FormTypeInterface')) {
                    throw new InvalidArgumentException(sprintf('Could not load type "%s": class does not implement "Symfony\Component\Form\FormTypeInterface".', $name));
                }

                $type = new $name();
            }

            $this->types[$name] = $this->resolveType($type);
        }

        return $this->types[$name];
    }

    /**
     * Wraps a type into a ResolvedFormTypeInterface implementation and connects
     * it with its parent type.
     *
     * @param FormTypeInterface $type The type to resolve
     *
     * @return ResolvedFormTypeInterface The resolved type
     */
    private function resolveType(FormTypeInterface $type)
    {
        $typeExtensions = array();
        $parentType = $type->getParent();
        $fqcn = get_class($type);

        if (isset($this->checkedTypes[$fqcn])) {
            $types = implode(' > ', array_merge(array_keys($this->checkedTypes), array($fqcn)));
            throw new LogicException(sprintf('Circular reference detected for form type "%s" (%s).', $fqcn, $types));
        }

        $this->checkedTypes[$fqcn] = true;

        try {
            foreach ($this->extensions as $extension) {
                $typeExtensions = array_merge(
                    $typeExtensions,
                    $extension->getTypeExtensions($fqcn)
                );
            }

            return $this->resolvedTypeFactory->createResolvedType(
                $type,
                $typeExtensions,
                $parentType ? $this->getType($parentType) : null
            );
        } finally {
            unset($this->checkedTypes[$fqcn]);
        }
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
        } catch (ExceptionInterface $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser()
    {
        if (false === $this->guesser) {
            $guessers = array();

            foreach ($this->extensions as $extension) {
                $guesser = $extension->getTypeGuesser();

                if ($guesser) {
                    $guessers[] = $guesser;
                }
            }

            $this->guesser = !empty($guessers) ? new FormTypeGuesserChain($guessers) : null;
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
