<?php

return array(
    new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
    new Symfony\Bundle\SecurityBundle\SecurityBundle(),
    new Symfony\Bundle\TwigBundle\TwigBundle(),
    new Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\CsrfFormLoginBundle(),
);
