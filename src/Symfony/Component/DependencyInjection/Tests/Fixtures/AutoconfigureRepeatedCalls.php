<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(calls: [['setBar', ['arg2']]])]
#[Autoconfigure(calls: [['setFoo', ['arg1']]])]
class AutoconfigureRepeatedCalls
{
    public function setFoo(string $arg)
    {
    }

    public function setBar(string $arg)
    {
    }
}
