<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

class AmbiguousDefinition extends Definition
{
    private $services;

    public function __construct($class, array $services)
    {
        parent::__construct($class, [$class, $services]);
        $this->setFactory([AmbiguousService::class, 'throwException']);
        $this->services = $services;
    }

    public function getServices()
    {
        return $this->services;
    }
}
