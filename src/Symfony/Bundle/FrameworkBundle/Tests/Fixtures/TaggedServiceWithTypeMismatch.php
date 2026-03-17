<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

class TaggedServiceWithTypeMismatch
{
    public function __construct(\DateTimeInterface $date)
    {
    }
}
