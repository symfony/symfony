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
use Symfony\Component\DependencyInjection\ContainerInterface;

class DependencyInjectionExtension implements FormExtensionInterface
{
    private $container;

    private $typeServiceIds;

    private $guesserServiceIds;

    private $guesser;

    private $guesserLoaded = false;

    public function __construct(ContainerInterface $container,
        array $typeServiceIds, array $typeExtensionServiceIds,
        array $guesserServiceIds)
    {
        $this->container = $container;
        $this->typeServiceIds = $typeServiceIds;
        $this->typeExtensionServiceIds = $typeExtensionServiceIds;
        $this->guesserServiceIds = $guesserServiceIds;
    }

    public function getType($name)
    {
        if (!isset($this->typeServiceIds[$name])) {
            throw new \InvalidArgumentException(sprintf('The field type "%s" is not registered with the service container.', $name));
        }

        return $this->container->get($this->typeServiceIds[$name]);
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
                $extensions[] = $this->container->get($serviceId);
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

            if (count($guessers) > 0) {
                $this->guesser = new FormTypeGuesserChain($guessers);
            }
        }

        return $this->guesser;
    }
}
