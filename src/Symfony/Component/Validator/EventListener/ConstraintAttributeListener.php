<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ControllerArgument;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Handles the validator constraint attributes on controller's arguments.
 *
 * @author Dynèsh Hassanaly <artyum@protonmail.com>
 */
class ConstraintAttributeListener implements EventSubscriberInterface
{
    public function __construct(private readonly ValidatorInterface $validator) {}

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $controller = $event->getController();
        $arguments = $event->getArguments();
        $reflectionMethod = $this->getReflectionMethod($controller);

        foreach ($reflectionMethod->getParameters() as $index => $reflectionParameter) {
            $reflectionAttributes = $reflectionParameter->getAttributes(ControllerArgument::class);

            if (!$reflectionAttributes) {
                continue;
            }

            foreach ($reflectionAttributes as $reflectionAttribute) {
                /** @var Constraint $constraint */
                $constraint = $reflectionAttribute->newInstance();
                $value = $arguments[$index];
                $violations = $this->validator->validate($value, $constraint);

                if ($violations->count() > 0) {
                    throw new ValidationFailedException($value, $violations);
                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 10]];
    }

    private function getReflectionMethod(callable $controller): \ReflectionMethod
    {
        if (is_array($controller)) {
            $class = $controller[0];
            $method = $controller[1];
        } else {
            /** @var object $controller */
            $class = $controller;
            $method = '__invoke';
        }

        return new \ReflectionMethod($class, $method);
    }
}
