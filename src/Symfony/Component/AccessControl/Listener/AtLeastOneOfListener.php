<?php

namespace Symfony\Component\AccessControl\Listener;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessControlManager;
use Symfony\Component\AccessControl\Attribute\All;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\AccessControl\MetadataBag;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @experimental
 */
final readonly class AtLeastOneOfListener implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AccessControlManager $accessControlManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20],
            ConsoleEvents::COMMAND => ['onConsoleCommand', 20],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command === null) {
            return;
        }
        $reflectionClass = new \ReflectionClass($command);
        $attributes = $reflectionClass->getAttributes(All::class);

        foreach ($attributes as $attribute) {
            $this->processAttribute(
                $attribute->newInstance(),
                [
                    'input' => $event->getInput(),
                    'output' => $event->getOutput()
                ]
            );
        }
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        /** @var All[] $attributes */
        if (!\is_array($attributes = $event->getAttributes()[All::class] ?? null)) {
            return;
        }

        foreach ($attributes as $attribute) {
            $this->processAttribute(
                $attribute,
                [
                    'request' => $event->getRequest(),
                    'args' => $event->getArguments(),
                ]
            );
        }
    }

    private function processAttribute(All $attribute, array $metadata): void
    {
        foreach ($attribute->accessPolicies as $accessPolicy) {
            $accessRequest = new AccessRequest(
                $this->tokenStorage->getToken(),
                $accessPolicy->attribute,
                $accessPolicy->subject,
                new MetadataBag([
                    ...$accessPolicy->metadata,
                    ...$metadata,
                ]),
                $accessPolicy->allowIfAllAbstain
            );
            $accessDecision = $this->accessControlManager->decide($accessRequest, $accessPolicy->strategy);

            if ($accessDecision->decision === DecisionVote::ACCESS_GRANTED) {
                return;
            }
        }

        if ($statusCode = $attribute->statusCode) {
            throw new HttpException($statusCode, $attribute->message, code: $attribute->exceptionCode ?? 0);
        }

        throw new AccessDeniedException(
            $attribute->message,
            null,
            $attribute->exceptionCode ?? 403
        );
    }
}
