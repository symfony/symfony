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

if (interface_exists(LegacyServiceSubscriberInterface::class)) {
    require __DIR__.\DIRECTORY_SEPARATOR.'CompatibilityServiceSubscriberInterface.legacy.php';
} else {
    require __DIR__.\DIRECTORY_SEPARATOR.'CompatibilityServiceSubscriberInterface.contracts.php';
}
