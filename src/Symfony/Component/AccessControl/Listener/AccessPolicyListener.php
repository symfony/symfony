<?php

namespace Symfony\Component\AccessControl\Listener;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessControlManager;
use Symfony\Component\AccessControl\Attribute\AccessPolicy;
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
final readonly class AccessPolicyListener implements EventSubscriberInterface
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
        $attributes = $reflectionClass->getAttributes(AccessPolicy::class);

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
        /** @var AccessPolicy[] $attributes */
        if (!\is_array($attributes = $event->getAttributes()[AccessPolicy::class] ?? null)) {
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

    private function processAttribute(AccessPolicy $attribute, array $metadata): void
    {
        $accessRequest = new AccessRequest(
            $this->tokenStorage->getToken(),
            $attribute->attribute,
            $attribute->subject,
            new MetadataBag([
                ...$attribute->metadata,
                ...$metadata,
            ]),
            $attribute->allowIfAllAbstain
        );
        $accessDecision = $this->accessControlManager->decide($accessRequest, $attribute->strategy);

        if ($accessDecision->decision !== DecisionVote::ACCESS_GRANTED) {
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
}
