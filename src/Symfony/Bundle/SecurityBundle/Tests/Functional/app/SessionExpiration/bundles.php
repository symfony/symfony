<?php

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\FormLoginBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return array(
    new FrameworkBundle(),
    new SecurityBundle(),
    new TwigBundle(),
    new FormLoginBundle(),
);
