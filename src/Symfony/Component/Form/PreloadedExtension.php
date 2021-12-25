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
    private array $types = [];
    private array $typeExtensions = [];
    private ?FormTypeGuesserInterface $typeGuesser;

    /**
     * Creates a new preloaded extension.
     *
     * @param FormTypeInterface[]            $types          The types that the extension should support
     * @param FormTypeExtensionInterface[][] $typeExtensions The type extensions that the extension should support
     */
    public function __construct(array $types, array $typeExtensions, FormTypeGuesserInterface $typeGuesser = null)
    {
        $this->typeExtensions = $typeExtensions;
        $this->typeGuesser = $typeGuesser;

        foreach ($types as $type) {
            $this->types[\get_class($type)] = $type;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getType(string $name): FormTypeInterface
    {
        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(sprintf('The type "%s" cannot be loaded by this extension.', $name));
        }

        return $this->types[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions(string $name): array
    {
        return $this->typeExtensions[$name]
            ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeExtensions(string $name): bool
    {
        return !empty($this->typeExtensions[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser(): ?FormTypeGuesserInterface
    {
        return $this->typeGuesser;
    }
}
