<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Service;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class LegacyParentTestService
{
    public function aParentService(): Service1
    {
    }

    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        return $container;
    }
}

class LegacyTestService extends LegacyParentTestService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    protected $container;

    #[SubscribedService]
    public function aService(): Service2
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService(nullable: true)]
    public function nullableInAttribute(): Service2
    {
        if (!$this->container->has(__METHOD__)) {
            throw new \LogicException();
        }

        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    public function nullableReturnType(): ?Service2
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService(attributes: new Required())]
    public function withAttribute(): ?Service2
    {
        return $this->container->get(__METHOD__);
    }
}

class LegacyChildTestService extends LegacyTestService
{
    #[SubscribedService]
    public function aChildService(): LegacyService3
    {
        return $this->container->get(__METHOD__);
    }
}

class LegacyParentWithMagicCall
{
    public function __call($method, $args)
    {
        throw new \BadMethodCallException('Should not be called.');
    }

    public static function __callStatic($method, $args)
    {
        throw new \BadMethodCallException('Should not be called.');
    }
}

class LegacyService3
{
}

class LegacyParentTestService2
{
    /** @var ContainerInterface */
    protected $container;

    public function setContainer(ContainerInterface $container)
    {
        $previous = $this->container ?? null;
        $this->container = $container;

        return $previous;
    }
}
