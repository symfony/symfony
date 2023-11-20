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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\TypedReference;
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
        if (\is_string($services)) {
            parent::__construct(new ServiceLocatorArgument(new TaggedIteratorArgument($services, $indexAttribute, $defaultIndexMethod, true, $defaultPriorityMethod, (array) $exclude, $excludeSelf)));

            return;
        }

        $references = [];

        foreach ($services as $key => $type) {
            $attributes = [];

            if ($type instanceof Autowire) {
                $references[$key] = $type;
                continue;
            }

            if ($type instanceof SubscribedService) {
                $key = $type->key ?? $key;
                $attributes = $type->attributes;
                $type = ($type->nullable ? '?' : '').($type->type ?? throw new InvalidArgumentException(sprintf('When "%s" is used, a type must be set.', SubscribedService::class)));
            }

            if (!\is_string($type) || !preg_match('/(?(DEFINE)(?<cn>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))(?(DEFINE)(?<fqcn>(?&cn)(?:\\\\(?&cn))*+))^\??(?&fqcn)(?:(?:\|(?&fqcn))*+|(?:&(?&fqcn))*+)$/', $type)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a PHP type for key "%s".', \is_string($type) ? $type : get_debug_type($type), $key));
            }
            $optionalBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if ('?' === $type[0]) {
                $type = substr($type, 1);
                $optionalBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            }
            if (\is_int($name = $key)) {
                $key = $type;
                $name = null;
            }

            $references[$key] = new TypedReference($type, $type, $optionalBehavior, $name, $attributes);
        }

        parent::__construct(new ServiceLocatorArgument($references));
    }
}
