<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Service\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class HookedPropertyService implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService]
    public MyDependency $myDependency {
        get => $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    public ?MyDependency $myNullableDependency {
        get => $this->container->has(__METHOD__) ? $this->container->get(__METHOD__) : null;
    }

    #[SubscribedService(nullable: true)]
    public MyDependency $myTentativeDependency {
        get => $this->container->has(__METHOD__) ? $this->container->get(__METHOD__) : throw new \LogicException('Dependency not found');
    }

    #[SubscribedService]
    public MyDependency $myCachedDependency {
        get => $this->myCachedDependency ??= $this->container->get(__METHOD__);
    }

    #[SubscribedService(key: 'my_key')]
    public MyDependency $myKeyedDependency {
        get => $this->container->get('my_key');
    }
}

final class MyDependency
{
}
