<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ServiceSubscriberInterface as LegacyServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

if (interface_exists(LegacyServiceSubscriberInterface::class)) {
    /**
     * @internal
     */
    interface CompatibilityServiceSubscriberInterface extends LegacyServiceSubscriberInterface
    {
    }
} else {
    /**
     * @internal
     */
    interface CompatibilityServiceSubscriberInterface extends ServiceSubscriberInterface
    {
    }
}
