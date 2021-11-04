<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\SchemaListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;
use Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

/**
 * Automatically adds the rememberme table needed for the {@see DoctrineTokenProvider}.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class RememberMeTokenProviderDoctrineSchemaSubscriber implements EventSubscriber
{
    private $rememberMeHandlers;

    /**
     * @param iterable<mixed, RememberMeHandlerInterface> $rememberMeHandlers
     */
    public function __construct(iterable $rememberMeHandlers)
    {
        $this->rememberMeHandlers = $rememberMeHandlers;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $dbalConnection = $event->getEntityManager()->getConnection();

        foreach ($this->rememberMeHandlers as $rememberMeHandler) {
            if (
                $rememberMeHandler instanceof PersistentRememberMeHandler
                && ($tokenProvider = $rememberMeHandler->getTokenProvider()) instanceof DoctrineTokenProvider
            ) {
                $tokenProvider->configureSchema($event->getSchema(), $dbalConnection);
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        if (!class_exists(ToolEvents::class)) {
            return [];
        }

        return [
            ToolEvents::postGenerateSchema,
        ];
    }
}
