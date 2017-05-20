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
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Exception\InvalidArgumentException;

class DependencyInjectionExtension implements FormExtensionInterface
{
    private $guesser;
    private $guesserLoaded = false;
    private $typeContainer;
    private $typeExtensionServices;
    private $guesserServices;

    // @deprecated to be removed in Symfony 4.0
    private $typeServiceIds;
    private $guesserServiceIds;

    /**
     * Constructor.
     *
     * @param ContainerInterface $typeContainer
     * @param iterable[]         $typeExtensionServices
     * @param iterable           $guesserServices
     */
    public function __construct(ContainerInterface $typeContainer, array $typeExtensionServices, $guesserServices, array $guesserServiceIds = null)
    {
        if (null !== $guesserServiceIds) {
            @trigger_error(sprintf('Passing four arguments to the %s::__construct() method is deprecated since Symfony 3.3 and will be disallowed in Symfony 4.0. The new constructor only accepts three arguments.', __CLASS__), E_USER_DEPRECATED);
            $this->guesserServiceIds = $guesserServiceIds;
            $this->typeServiceIds = $typeExtensionServices;
            $typeExtensionServices = $guesserServices;
            $guesserServices = $guesserServiceIds;
        }

        $this->typeContainer = $typeContainer;
        $this->typeExtensionServices = $typeExtensionServices;
        $this->guesserServices = $guesserServices;
    }

    public function getType($name)
    {
        if (null !== $this->guesserServiceIds) {
            if (!isset($this->typeServiceIds[$name])) {
                throw new InvalidArgumentException(sprintf('The field type "%s" is not registered in the service container.', $name));
            }

            return $this->typeContainer->get($this->typeServiceIds[$name]);
        }

        if (!$this->typeContainer->has($name)) {
            throw new InvalidArgumentException(sprintf('The field type "%s" is not registered in the service container.', $name));
        }

        return $this->typeContainer->get($name);
    }

    public function hasType($name)
    {
        if (null !== $this->guesserServiceIds) {
            return isset($this->typeServiceIds[$name]);
        }

        return $this->typeContainer->has($name);
    }

    public function getTypeExtensions($name)
    {
        $extensions = array();

        if (isset($this->typeExtensionServices[$name])) {
            foreach ($this->typeExtensionServices[$name] as $serviceId => $extension) {
                if (null !== $this->guesserServiceIds) {
                    $extension = $this->typeContainer->get($serviceId = $extension);
                }

                $extensions[] = $extension;

                // validate result of getExtendedType() to ensure it is consistent with the service definition
                if ($extension->getExtendedType() !== $name) {
                    throw new InvalidArgumentException(
                        sprintf('The extended type specified for the service "%s" does not match the actual extended type. Expected "%s", given "%s".',
                            $serviceId,
                            $name,
                            $extension->getExtendedType()
                        )
                    );
                }
            }
        }

        return $extensions;
    }

    public function hasTypeExtensions($name)
    {
        return isset($this->typeExtensionServices[$name]);
    }

    public function getTypeGuesser()
    {
        if (!$this->guesserLoaded) {
            $this->guesserLoaded = true;
            $guessers = array();

            foreach ($this->guesserServices as $serviceId => $service) {
                if (null !== $this->guesserServiceIds) {
                    $service = $this->typeContainer->get($serviceId = $service);
                }

                $guessers[] = $service;
            }

            if ($guessers) {
                $this->guesser = new FormTypeGuesserChain($guessers);
            }
        }

        return $this->guesser;
    }
}
