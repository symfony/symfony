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

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
class VarDumperServerListenerForV7 implements TestListener, ContextProviderInterface
{
    use TestListenerDefaultImplementation;

    private $trait;

    public function __construct(string $host = null)
    {
        $this->trait = new VarDumperServerListenerTrait($host);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->trait->startTest($suite);
    }

    public function getContext(): ?array
    {
        $this->trait->getContext();
    }
}
