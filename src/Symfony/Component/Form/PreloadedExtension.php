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
 * A form extension with preloaded types, type extensions and type guessers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PreloadedExtension implements FormExtensionInterface
{
    private $types = array();
    private $typeExtensions = array();
    private $typeGuesser;

    /**
     * Creates a new preloaded extension.
     *
     * @param FormTypeInterface[]            $types          The types that the extension should support
     * @param FormTypeExtensionInterface[][] $typeExtensions The type extensions that the extension should support
     * @param FormTypeGuesserInterface|null  $typeGuesser    The guesser that the extension should support
     */
    public function __construct(array $types, array $typeExtensions, FormTypeGuesserInterface $typeGuesser = null)
    {
        foreach ($typeExtensions as $extensions) {
            foreach ($extensions as $typeExtension) {
                if (!method_exists($typeExtension, 'getExtendedTypes')) {
                    @trigger_error(sprintf('Not implementing the static getExtendedTypes() method in %s when implementing the %s is deprecated since Symfony 4.2. The method will be added to the interface in 5.0.', \get_class($typeExtension), FormTypeExtensionInterface::class), E_USER_DEPRECATED);
                }
            }
        }

        $this->typeExtensions = $typeExtensions;
        $this->typeGuesser = $typeGuesser;

        foreach ($types as $type) {
            $this->types[\get_class($type)] = $type;
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
