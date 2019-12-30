<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NamedArgumentsDummy
{
    public function __construct(CaseSensitiveClass $c, $apiKey, $hostName, ContainerInterface $interface, iterable $objects)
    {
    }

    public function setApiKey($apiKey)
    {
    }

    public function setSensitiveClass(CaseSensitiveClass $c)
    {
    }

    public function setAnotherC($c)
    {
    }
}
