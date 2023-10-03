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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Autowires a service locator based on a tag name or an explicit list of key => service-type pairs.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireLocator extends Autowire
{
    /**
     * @see ServiceSubscriberInterface::getSubscribedServices()
     *
     * @param string|array<string|SubscribedService> $services An explicit list of services or a tag name
     * @param string|string[]                        $exclude  A service or a list of services to exclude
     */
    public function __construct(
        string|array $services,
        string $indexAttribute = null,
        string $defaultIndexMethod = null,
        string $defaultPriorityMethod = null,
        string|array $exclude = [],
        bool $excludeSelf = true,
    ) {
        $iterator = (new AutowireIterator($services, $indexAttribute, $defaultIndexMethod, $defaultPriorityMethod, (array) $exclude, $excludeSelf))->value;

        if ($iterator instanceof TaggedIteratorArgument) {
            $iterator = new TaggedIteratorArgument($iterator->getTag(), $iterator->getIndexAttribute(), $iterator->getDefaultIndexMethod(), true, $iterator->getDefaultPriorityMethod(), $iterator->getExclude(), $iterator->excludeSelf());
        } elseif ($iterator instanceof IteratorArgument) {
            $iterator = $iterator->getValues();
        }

        parent::__construct(new ServiceLocatorArgument($iterator));
    }
}
