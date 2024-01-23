<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Service tag to autoconfigure routing condition services.
 *
 * You can tag a service:
 *
 *     #[AsRoutingConditionService('foo')]
 *     class SomeFooService
 *     {
 *         public function bar(): bool
 *         {
 *             // ...
 *         }
 *     }
 *
 * Then you can use the tagged service in the routing condition:
 *
 *     class PageController
 *     {
 *         #[Route('/page', condition: "service('foo').bar()")]
 *         public function page(): Response
 *         {
 *             // ...
 *         }
 *     }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsRoutingConditionService extends AutoconfigureTag
{
    public function __construct(
        ?string $alias = null,
        int $priority = 0,
    ) {
        parent::__construct('routing.condition_service', ['alias' => $alias, 'priority' => $priority]);
    }
}
