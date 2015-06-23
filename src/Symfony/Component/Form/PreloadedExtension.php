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

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * A form extension with preloaded types, type exceptions and type guessers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PreloadedExtension implements FormExtensionInterface
{
    /**
     * @var FormTypeInterface[]
     */
    private $types = array();

    /**
     * @var array[FormTypeExtensionInterface[]]
     */
    private $typeExtensions = array();

    /**
     * @var FormTypeGuesserInterface
     */
    private $typeGuesser;

    /**
     * Creates a new preloaded extension.
     *
     * @param FormTypeInterface[]            $types          The types that the extension should support.
     * @param FormTypeExtensionInterface[][] $typeExtensions The type extensions that the extension should support.
     * @param FormTypeGuesserInterface|null  $typeGuesser    The guesser that the extension should support.
     */
    public function __construct(array $types, array $typeExtensions, FormTypeGuesserInterface $typeGuesser = null)
    {
        $this->typeExtensions = $typeExtensions;
        $this->typeGuesser = $typeGuesser;

        foreach ($types as $type) {
            // Up to Symfony 2.8, types were identified by their names
            $this->types[$type->getName()] = $type;

            // Since Symfony 2.8, types are identified by their FQCN
            $this->types[get_class($type)] = $type;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getType($name)
    {
        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(sprintf('The type "%s" can not be loaded by this extension', $name));
        }

        return $this->types[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasType($name)
    {
        return isset($this->types[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions($name)
    {
        return isset($this->typeExtensions[$name])
            ? $this->typeExtensions[$name]
            : array();
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeExtensions($name)
    {
        return !empty($this->typeExtensions[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser()
    {
        return $this->typeGuesser;
    }
}
