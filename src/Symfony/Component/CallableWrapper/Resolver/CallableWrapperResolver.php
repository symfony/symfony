<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Resolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttributeInterface;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;
use Symfony\Contracts\Service\ServiceLocatorTrait;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @internal
 */
class CallableWrapperResolver implements CallableWrapperResolverInterface, ContainerInterface
{
    use ServiceLocatorTrait;

    public function resolve(CallableWrapperAttributeInterface $attribute): CallableWrapperInterface
    {
        $id = $attribute->wrappedBy();

        if ($this->has($id)) {
            return $this->get($id);
        }

        if ($attribute::class === $id && $attribute instanceof CallableWrapperInterface) {
            return $attribute;
        }

        if (class_exists($id)) {
            return new $id();
        }

        return $this->get($id); // let it throw
    }
}
