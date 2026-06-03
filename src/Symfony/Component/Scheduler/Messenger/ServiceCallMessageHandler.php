<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Psr\Container\ContainerInterface;

/**
 * Handler to call any service.
 *
 * @author valtzu <valtzu@gmail.com>
 */
class ServiceCallMessageHandler
{
    public function __construct(private readonly ContainerInterface $serviceLocator)
    {
    }

    public function __invoke(ServiceCallMessage $message): mixed
    {
        return $this->serviceLocator->get($message->getServiceId())->{$message->getMethod()}(...$message->getArguments());
    }
}
