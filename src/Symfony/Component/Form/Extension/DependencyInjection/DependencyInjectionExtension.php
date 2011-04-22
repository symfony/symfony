<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
        array $typeServiceIds, array $guesserServiceIds)
    {
        $this->container = $container;
        $this->typeServiceIds = $typeServiceIds;
        $this->guesserServiceIds = $guesserServiceIds;
    }

    public function getType($identifier)
    {
        if (!isset($this->typeServiceIds[$identifier])) {
            throw new \InvalidArgumentException(sprintf('The field type "%s" is not registered with the service container.', $identifier));
        }

        return $this->container->get($this->typeServiceIds[$identifier]);
    }

    public function hasType($identifier)
    {
        return isset($this->typeServiceIds[$identifier]);
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
