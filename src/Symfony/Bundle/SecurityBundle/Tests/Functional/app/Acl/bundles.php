<?php

return array(
    new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
    new Symfony\Bundle\SecurityBundle\SecurityBundle(),
    new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
    new Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AclBundle\AclBundle(),
);
