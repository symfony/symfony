<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class can be used as a base class for functional tests that need to
 * access the service container.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ContainerAwareTestCase extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private static $container;

    /**
     * Returns the service container.
     *
     * @param array $options Options to pass to the KernelTestCase::createKernel() method. May contain the
     *                       'environment' and 'debug' keys that will be used to create the underlying kernel.
     *
     * @return ContainerInterface
     */
    public static function getContainer(array $options = array())
    {
        if (null === self::$container) {
            static::bootKernel($options);
            self::$container = static::$kernel->getContainer();
        }

        return self::$container;
    }

    /**
     * Release container after test
     */
    protected function tearDown()
    {
        self::$container = null;
        parent::tearDown();
    }

}
