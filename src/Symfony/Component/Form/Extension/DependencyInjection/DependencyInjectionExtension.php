<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;

class DependencyInjectionExtension implements FormExtensionInterface
{
    private ?FormTypeGuesserChain $guesser = null;
    private bool $guesserLoaded = false;
    private ContainerInterface $typeContainer;
    private array $typeExtensionServices;
    private iterable $guesserServices;

    /**
     * @param iterable[] $typeExtensionServices
     */
    public function __construct(ContainerInterface $typeContainer, array $typeExtensionServices, iterable $guesserServices)
    {
        $this->typeContainer = $typeContainer;
        $this->typeExtensionServices = $typeExtensionServices;
        $this->guesserServices = $guesserServices;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(string $name): FormTypeInterface
    {
        if (!$this->typeContainer->has($name)) {
            throw new InvalidArgumentException(sprintf('The field type "%s" is not registered in the service container.', $name));
        }

        return $this->typeContainer->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasType(string $name): bool
    {
        return $this->typeContainer->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions(string $name): array
    {
        $extensions = [];

        if (isset($this->typeExtensionServices[$name])) {
            foreach ($this->typeExtensionServices[$name] as $extension) {
                $extensions[] = $extension;

                $extendedTypes = [];
                foreach ($extension::getExtendedTypes() as $extendedType) {
                    $extendedTypes[] = $extendedType;
                }

                // validate the result of getExtendedTypes() to ensure it is consistent with the service definition
                if (!\in_array($name, $extendedTypes, true)) {
                    throw new InvalidArgumentException(sprintf('The extended type "%s" specified for the type extension class "%s" does not match any of the actual extended types (["%s"]).', $name, \get_class($extension), implode('", "', $extendedTypes)));
                }
            }
        }

        return $extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeExtensions(string $name): bool
    {
        return isset($this->typeExtensionServices[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser(): ?FormTypeGuesserInterface
    {
        if (!$this->guesserLoaded) {
            $this->guesserLoaded = true;
            $guessers = [];

            foreach ($this->guesserServices as $serviceId => $service) {
                $guessers[] = $service;
            }

            if ($guessers) {
                $this->guesser = new FormTypeGuesserChain($guessers);
            }
        }

        return $this->guesser;
    }
}
