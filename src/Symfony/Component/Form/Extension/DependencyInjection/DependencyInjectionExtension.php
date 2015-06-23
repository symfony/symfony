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

use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DependencyInjectionExtension implements FormExtensionInterface
{
    private $container;
    private $typeServiceIds;
    private $typeExtensionServiceIds;
    private $guesserServiceIds;
    private $legacyNames;
    private $guesser;
    private $guesserLoaded = false;

    public function __construct(ContainerInterface $container,
        array $typeServiceIds, array $typeExtensionServiceIds,
        array $guesserServiceIds, array $legacyNames = array())
    {
        $this->container = $container;
        $this->typeServiceIds = $typeServiceIds;
        $this->typeExtensionServiceIds = $typeExtensionServiceIds;
        $this->guesserServiceIds = $guesserServiceIds;
        $this->legacyNames = $legacyNames;
    }

    public function getType($name)
    {
        if (!isset($this->typeServiceIds[$name])) {
            throw new InvalidArgumentException(sprintf('The field type "%s" is not registered with the service container.', $name));
        }

        if (isset($this->legacyNames[$name])) {
            @trigger_error('Accessing form types by type name/service ID is deprecated since version 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead.', E_USER_DEPRECATED);
        }

        $type = $this->container->get($this->typeServiceIds[$name]);

        // BC: validate result of getName() for legacy names (non-FQCN)
        if (isset($this->legacyNames[$name]) && $type->getName() !== $name) {
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
        if (isset($this->typeServiceIds[$name])) {
            if (isset($this->legacyNames[$name])) {
                @trigger_error('Accessing form types by type name/service ID is deprecated since version 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead.', E_USER_DEPRECATED);
            }

            return true;
        }

        return false;
    }

    public function getTypeExtensions($name)
    {
        if (isset($this->legacyNames[$name])) {
            @trigger_error('Accessing form types by type name/service ID is deprecated since version 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead.', E_USER_DEPRECATED);
        }

        $extensions = array();

        if (isset($this->typeExtensionServiceIds[$name])) {
            foreach ($this->typeExtensionServiceIds[$name] as $serviceId) {
                $extensions[] = $this->container->get($serviceId);
            }
        }

        return $extensions;
    }

    public function hasTypeExtensions($name)
    {
        if (isset($this->legacyNames[$name])) {
            @trigger_error('Accessing form types by type name/service ID is deprecated since version 2.8 and will not be supported in 3.0. Use the fully-qualified type class name instead.', E_USER_DEPRECATED);
        }

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

            if (count($guessers) > 0) {
                $this->guesser = new FormTypeGuesserChain($guessers);
            }
        }

        return $this->guesser;
    }
}
