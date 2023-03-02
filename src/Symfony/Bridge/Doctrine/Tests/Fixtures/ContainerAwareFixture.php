<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareFixture implements FixtureInterface, ContainerAwareInterface
{
    public ?ContainerInterface $container = null;

    public function setContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager): void
    {
    }
}
