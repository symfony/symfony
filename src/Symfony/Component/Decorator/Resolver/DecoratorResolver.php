<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Resolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\Decorator\Attribute\DecoratorAttribute;
use Symfony\Component\Decorator\DecoratorInterface;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class DecoratorResolver implements DecoratorResolverInterface, ContainerInterface
{
    use ServiceLocatorTrait;

    public function resolve(DecoratorAttribute $metadata): DecoratorInterface
    {
        $id = $metadata->decoratedBy();

        if ($this->has($id)) {
            return $this->get($id);
        }

        if ($metadata::class === $id && $metadata instanceof DecoratorInterface) {
            return $metadata;
        }

        if (class_exists($id)) {
            return new $id();
        }

        return $this->get($id); // let it throw
    }
}
