<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

final class InvokableControllerWithUnion
{
    public function __invoke(int | \DateTime | string $date)
    {
    }
}
