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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserChain;

class DependencyInjectionExtension implements FormExtensionInterface
{
    private $container;
    private $typeServiceIds;
    private $typeExtensionServiceIds;
    private $guesserServiceIds;
    private $guesser;
    private $guesserLoaded = false;

    public function __construct(ContainerInterface $container, array $typeServiceIds, array $typeExtensionServiceIds, array $guesserServiceIds)
    {
        $this->container = $container;
        $this->typeServiceIds = $typeServiceIds;
        $this->typeExtensionServiceIds = $typeExtensionServiceIds;
        $this->guesserServiceIds = $guesserServiceIds;
    }

    public function getType($name)
    {
        if (!isset($this->typeServiceIds[$name])) {
            throw new InvalidArgumentException(sprintf('The field type "%s" is not registered with the service container.', $name));
        }

        $type = $this->container->get($this->typeServiceIds[$name]);

        // BC: validate result of getName() for legacy names (non-FQCN)
        if ($name !== \get_class($type) && $type->getName() !== $name) {
            throw new InvalidArgumentException(
                sprintf('The type name specified for the service "%s" does not match the actual name. Expected "%s", given "%s"',
                    $this->typeServiceIds[$name],
                    $name,
                    $type->getName()
                )
            );
        }

        return $type;
    }

    public function hasType($name)
    {
        return isset($this->typeServiceIds[$name]);
    }

    public function getTypeExtensions($name)
    {
        $extensions = array();

        if (isset($this->typeExtensionServiceIds[$name])) {
            foreach ($this->typeExtensionServiceIds[$name] as $serviceId) {
                $extensions[] = $extension = $this->container->get($serviceId);

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
        return isset($this->typeExtensionServiceIds[$name]);
    }

    public function getTypeGuesser()
    {
        if (!$this->guesserLoaded) {
            $this->guesserLoaded = true;
            $guessers = array();

            foreach ($this->guesserServiceIds as $serviceId) {
                $guessers[] = $this->container->get($serviceId);
            }

            if (\count($guessers) > 0) {
                $this->guesser = new FormTypeGuesserChain($guessers);
            }
        }

        return $this->guesser;
    }
}
