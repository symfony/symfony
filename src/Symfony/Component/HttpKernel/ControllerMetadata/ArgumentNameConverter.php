<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ArgumentNameConverter
{
    private $argumentMetadataFactory;

    public function __construct(ArgumentMetadataFactoryInterface $argumentMetadataFactory)
    {
        $this->argumentMetadataFactory = $argumentMetadataFactory;
    }

    /**
     * Returns an associative array of the controller arguments for the event.
     *
     * @return array
     */
    public function getControllerArguments(ControllerArgumentsEvent $event)
    {
        $namedArguments = $event->getRequest()->attributes->all();
        $argumentMetadatas = $this->argumentMetadataFactory->createArgumentMetadata($event->getController());
        $controllerArguments = $event->getArguments();

        foreach ($argumentMetadatas as $index => $argumentMetadata) {
            if ($argumentMetadata->isVariadic()) {
                // set the rest of the arguments as this arg's value
                $namedArguments[$argumentMetadata->getName()] = \array_slice($controllerArguments, $index);

                break;
            }

            if (!\array_key_exists($index, $controllerArguments)) {
                throw new \LogicException(sprintf('Could not find an argument value for argument %d of the controller.', $index));
            }

            $namedArguments[$argumentMetadata->getName()] = $controllerArguments[$index];
        }

        return $namedArguments;
    }
}
