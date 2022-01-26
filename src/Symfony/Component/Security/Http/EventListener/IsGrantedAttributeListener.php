<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentNameConverter;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles the IsGranted annotation on controllers.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class IsGrantedAttributeListener implements EventSubscriberInterface
{
    private $argumentNameConverter;
    private $authChecker;

    public function __construct(ArgumentNameConverter $argumentNameConverter, AuthorizationCheckerInterface $authChecker = null)
    {
        $this->argumentNameConverter = $argumentNameConverter;
        $this->authChecker = $authChecker;
    }

    public function onKernelControllerArguments(KernelEvent $event)
    {
        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return;
        }

        $className = \get_class($controller[0]);
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $classConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $object->getAttributes(IsGranted::class)
        );
        $methodConfigurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $method->getAttributes(IsGranted::class)
        );
        $configurations = array_merge($classConfigurations, $methodConfigurations);

        if (0 === count($configurations)) {
            return;
        }

        if (null === $this->authChecker) {
            throw new \LogicException('To use the @IsGranted tag, you need to install symfony/security-bundle and configure your security system.');
        }

        $arguments = $this->argumentNameConverter->getControllerArguments($event);

        foreach ($configurations as $configuration) {
            $subjectRef = $configuration->getSubject();
            $subject = null;

            if ($subjectRef) {
                if (\is_array($subjectRef)) {
                    foreach ($subjectRef as $ref) {
                        if (!\array_key_exists($ref, $arguments)) {
                            throw $this->createMissingSubjectException($ref);
                        }

                        $subject[$ref] = $arguments[$ref];
                    }
                } else {
                    if (!\array_key_exists($subjectRef, $arguments)) {
                        throw $this->createMissingSubjectException($subjectRef);
                    }

                    $subject = $arguments[$subjectRef];
                }
            }

            if (!$this->authChecker->isGranted($configuration->getAttributes(), $subject)) {
                $argsString = $this->getIsGrantedString($configuration);

                $message = $configuration->getMessage() ?: sprintf('Access Denied by controller annotation @IsGranted(%s)', $argsString);

                if ($statusCode = $configuration->getStatusCode()) {
                    throw new HttpException($statusCode, $message);
                }

                $accessDeniedException = new AccessDeniedException($message);
                $accessDeniedException->setAttributes($configuration->getAttributes());
                $accessDeniedException->setSubject($subject);

                throw $accessDeniedException;
            }
        }
    }

    private function createMissingSubjectException(string $subject)
    {
        return new \RuntimeException(sprintf('Could not find the subject "%s" for the @IsGranted annotation. Try adding a "$%s" argument to your controller method.', $subject, $subject));
    }

    private function getIsGrantedString(IsGranted $isGranted)
    {
        $attributes = array_map(function ($attribute) {
            return sprintf('"%s"', $attribute);
        }, (array) $isGranted->getAttributes());
        if (1 === \count($attributes)) {
            $argsString = reset($attributes);
        } else {
            $argsString = sprintf('[%s]', implode(', ', $attributes));
        }

        if (null !== $isGranted->getSubject()) {
            $argsString = sprintf('%s, %s', $argsString, $isGranted->getSubject());
        }

        return $argsString;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'];
    }
}
