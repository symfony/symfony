<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
class VarDumperServerListenerForV5 extends \PHPUnit_Framework_BaseTestListener implements ContextProviderInterface
{
    private $trait;

    public function __construct(string $host = null)
    {
        $this->trait = new VarDumperServerListenerTrait($host);
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        $this->trait->startTest($test);
    }

    public function getContext(): ?array
    {
        $this->trait->getContext();
    }
}
