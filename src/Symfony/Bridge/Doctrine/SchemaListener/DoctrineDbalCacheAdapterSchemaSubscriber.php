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

trigger_deprecation('symfony/doctrine-bridge', '6.3', 'The "%s" class is deprecated. Use "%s" instead.', DoctrineDbalCacheAdapterSchemaSubscriber::class, DoctrineDbalCacheAdapterSchemaListener::class);

/**
 * Automatically adds the cache table needed for the DoctrineDbalAdapter of
 * the Cache component.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @deprecated since Symfony 6.3, use {@link DoctrineDbalCacheAdapterSchemaListener} instead
 */
final class DoctrineDbalCacheAdapterSchemaSubscriber extends DoctrineDbalCacheAdapterSchemaListener implements EventSubscriber
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
