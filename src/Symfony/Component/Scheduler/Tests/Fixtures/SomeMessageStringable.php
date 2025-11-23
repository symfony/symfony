<?php

namespace Symfony\Component\Scheduler\Tests\Fixtures;

class SomeMessageStringable implements \Stringable
{
    public function __construct(public string $id)
    {
    }

    public function __toString()
    {
        return $this->id;
    }

}
