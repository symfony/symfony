<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireLocator extends Autowire
{
    public function __construct(string ...$serviceIds)
    {
        $values = [];

        foreach ($serviceIds as $key => $serviceId) {
            if ($nullable = str_starts_with($serviceId, '?')) {
                $serviceId = substr($serviceId, 1);
            }

            if (is_numeric($key)) {
                $key = $serviceId;
            }

            $values[$key] = new Reference(
                $serviceId,
                $nullable ? ContainerInterface::IGNORE_ON_INVALID_REFERENCE : ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            );
        }

        parent::__construct(new ServiceLocatorArgument($values));
    }
}
