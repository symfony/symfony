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
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles the IsGranted attribute on controllers.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class IsGrantedAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private AuthorizationCheckerInterface $authChecker,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event)
    {
        /** @var IsGranted[] $attributes */
        if (!\is_array($attributes = $event->getAttributes()[IsGranted::class] ?? null)) {
            return;
        }

        $arguments = $event->getNamedArguments();

        foreach ($attributes as $attribute) {
            $subjectRef = $attribute->subject;
            $subject = null;

            if ($subjectRef) {
                if (\is_array($subjectRef)) {
                    foreach ($subjectRef as $ref) {
                        if (!\array_key_exists($ref, $arguments)) {
                            throw new \RuntimeException(sprintf('Could not find the subject "%s" for the #[IsGranted] attribute. Try adding a "$%s" argument to your controller method.', $ref, $ref));
                        }
                        $subject[$ref] = $arguments[$ref];
                    }
                } elseif (!\array_key_exists($subjectRef, $arguments)) {
                    throw new \RuntimeException(sprintf('Could not find the subject "%s" for the #[IsGranted] attribute. Try adding a "$%s" argument to your controller method.', $subjectRef, $subjectRef));
                } else {
                    $subject = $arguments[$subjectRef];
                }
            }

            if (!$this->authChecker->isGranted($attribute->attributes, $subject)) {
                $message = $attribute->message ?: sprintf('Access Denied by #[IsGranted(%s)] on controller', $this->getIsGrantedString($attribute));

                if ($statusCode = $attribute->statusCode) {
                    throw new HttpException($statusCode, $message);
                }

                $accessDeniedException = new AccessDeniedException($message);
                $accessDeniedException->setAttributes($attribute->attributes);
                $accessDeniedException->setSubject($subject);

                throw $accessDeniedException;
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 10]];
    }

    private function getIsGrantedString(IsGranted $isGranted): string
    {
        $attributes = array_map(fn ($attribute) => '"'.$attribute.'"', (array) $isGranted->attributes);
        $argsString = 1 === \count($attributes) ? reset($attributes) : '['.implode(', ', $attributes).']';

        if (null !== $isGranted->subject) {
            $argsString .= ', "'.implode('", "', (array) $isGranted->subject).'"';
        }

        return $argsString;
    }
}
