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

use Symfony\Component\Routing\Attribute\AsRoutingConditionService as BaseAsRoutingConditionService;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Routing\Attribute\AsRoutingConditionService', BaseAsRoutingConditionService::class);

class_alias(BaseAsRoutingConditionService::class, 'Symfony\Bundle\FrameworkBundle\Routing\Attribute\AsRoutingConditionService');
