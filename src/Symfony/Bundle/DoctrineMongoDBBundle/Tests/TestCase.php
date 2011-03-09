<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\MongoDB\Connection;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\\ODM\\MongoDB\\Version')) {
            $this->markTestSkipped('Doctrine MongoDB ODM is not available.');
        }
    }

    /**
     * @return DocumentManager
     */
    protected function createTestDocumentManager($paths = array())
    {
        $config = new \Doctrine\ODM\MongoDB\Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setHydratorDir(\sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setHydratorNamespace('SymfonyTests\Doctrine');
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver($paths));
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        return DocumentManager::create(new Connection(), $config);
    }
}
