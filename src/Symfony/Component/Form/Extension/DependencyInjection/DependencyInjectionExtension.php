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
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $typeServiceIds;

    /**
     * @var array
     */
    private $typeExtensionServiceIds;

    /**
     * @var array
     */
    private $guesserServiceIds;

    private $guesser;

    /**
     * @var bool
     */
    private $guesserLoaded = false;

    /**
     * DependencyInjectionExtension constructor.
     *
     * @param ContainerInterface $container
     * @param array              $typeServiceIds
     * @param array              $typeExtensionServiceIds
     * @param array              $guesserServiceIds
     */
    public function __construct(ContainerInterface $container, array $typeServiceIds, array $typeExtensionServiceIds, array $guesserServiceIds)
    {
        $this->container = $container;
        $this->typeServiceIds = $typeServiceIds;
        $this->typeExtensionServiceIds = $typeExtensionServiceIds;
        $this->guesserServiceIds = $guesserServiceIds;
    }

    /**
     * @param string $name
     *
     * @return object
     */
    public function getType($name)
    {
        if (!isset($this->typeServiceIds[$name])) {
            throw new InvalidArgumentException(sprintf('The field type "%s" is not registered with the service container.', $name));
        }

        return $this->container->get($this->typeServiceIds[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasType($name)
    {
        return isset($this->typeServiceIds[$name]);
    }

    /**
     * @param string $name
     *
     * @return array
     */
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

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasTypeExtensions($name)
    {
        return isset($this->typeExtensionServiceIds[$name]);
    }

    /**
     * @return FormTypeGuesserChain
     */
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
