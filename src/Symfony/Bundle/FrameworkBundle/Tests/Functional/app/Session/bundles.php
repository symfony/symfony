<?php

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return array(
    new FrameworkBundle(),
    new TwigBundle(),
    new TestBundle(),
);
