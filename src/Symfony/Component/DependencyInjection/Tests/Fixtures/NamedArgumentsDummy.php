<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NamedArgumentsDummy
{
    public function __construct(CaseSensitiveClass $c, $apiKey, $hostName)
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
