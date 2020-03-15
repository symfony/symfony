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
    private $types = [];
    private $typeExtensions = [];
    private $typeGuesser;

    /**
     * Creates a new preloaded extension.
     *
     * @param FormTypeInterface[]            $types          The types that the extension should support
     * @param FormTypeExtensionInterface[][] $typeExtensions The type extensions that the extension should support
     */
    public function __construct(array $types, array $typeExtensions, FormTypeGuesserInterface $typeGuesser = null)
    {
        foreach ($typeExtensions as $extensions) {
            foreach ($extensions as $typeExtension) {
                if (!method_exists($typeExtension, 'getExtendedTypes')) {
                    @trigger_error(sprintf('Not implementing the "%s::getExtendedTypes()" method in "%s" is deprecated since Symfony 4.2.', FormTypeExtensionInterface::class, \get_class($typeExtension)), E_USER_DEPRECATED);
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
            throw new InvalidArgumentException(sprintf('The type "%s" can not be loaded by this extension.', $name));
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
            : [];
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
