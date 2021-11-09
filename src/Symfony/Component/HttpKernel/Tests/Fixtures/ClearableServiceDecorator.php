<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Contracts\Service\ResetInterface;

class ClearableServiceDecorator implements ClearableInterface, ResetInterface
{
    public static $counter = 0;

    private $clearableService;

    public function __construct(ClearableInterface $clearableService)
    {
        $this->clearableService = $clearableService;
    }

    public function clear()
    {
        ++self::$counter;

        $this->clearableService->clear();
    }

    public function reset()
    {
        self::$counter = 0;
    }
}
