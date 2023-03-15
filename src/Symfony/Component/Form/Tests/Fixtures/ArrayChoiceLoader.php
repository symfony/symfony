<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class ArrayChoiceLoader extends CallbackChoiceLoader
{
    public function __construct(array $choices = [])
    {
        parent::__construct(static fn (): array => $choices);
    }
}
