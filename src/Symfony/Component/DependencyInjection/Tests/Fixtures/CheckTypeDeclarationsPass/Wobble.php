<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class Wobble
{
    private $waldo;

    public function __construct(WaldoInterface $waldo)
    {
        $this->waldo = $waldo;
    }
}
