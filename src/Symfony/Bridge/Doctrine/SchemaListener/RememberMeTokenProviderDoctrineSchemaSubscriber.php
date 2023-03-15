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
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;

trigger_deprecation('symfony/doctrine-bridge', '6.3', 'The "%s" class is deprecated. Use "%s" instead.', RememberMeTokenProviderDoctrineSchemaSubscriber::class, RememberMeTokenProviderDoctrineSchemaListener::class);

/**
 * Automatically adds the rememberme table needed for the {@see DoctrineTokenProvider}.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @deprecated since Symfony 6.3, use {@link RememberMeTokenProviderDoctrineSchemaListener} instead
 */
final class RememberMeTokenProviderDoctrineSchemaSubscriber extends RememberMeTokenProviderDoctrineSchemaListener implements EventSubscriber
{
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
