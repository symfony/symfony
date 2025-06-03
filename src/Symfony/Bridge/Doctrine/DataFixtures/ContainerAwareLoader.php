<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

trigger_deprecation('symfony/dependency-injection', '6.4', '"%s" is deprecated, use dependency injection in your fixtures instead.', ContainerAwareLoader::class);

/**
 * Doctrine data fixtures loader that injects the service container into
 * fixture objects that implement ContainerAwareInterface.
 *
 * Note: Use of this class requires the Doctrine data fixtures extension, which
 * is a suggested dependency for Symfony.
 *
 * @deprecated since Symfony 6.4, use dependency injection in your fixtures instead
 */
class ContainerAwareLoader extends Loader
{
    use AddFixtureImplementation;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    private function doAddFixture(FixtureInterface $fixture): void
    {
        if ($fixture instanceof ContainerAwareInterface) {
            $fixture->setContainer($this->container);
        }

        parent::addFixture($fixture);
    }
}
