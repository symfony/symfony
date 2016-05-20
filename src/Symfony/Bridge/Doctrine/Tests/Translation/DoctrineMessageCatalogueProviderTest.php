<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider\Tests;

use Symfony\Component\Translation\Tests\MessageCatalogueProvider\CachedMessageCatalogueProviderTest;
use Symfony\Bridge\Doctrine\Translation\DoctrineMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;
use Doctrine\Common\Cache\ArrayCache;

class DoctrineMessageCatalogueProviderTest extends CachedMessageCatalogueProviderTest
{
    private $cache;

    protected function setUp()
    {
        if (!interface_exists('Doctrine\Common\Cache\Cache')) {
            $this->markTestSkipped('The "Doctrine Cache" is not available');
        }

        $this->cache = new ArrayCache();
    }

    protected function getMessageCatalogueProvider($debug, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $resourceCatalogue = new ResourceMessageCatalogueProvider($loaders, $resources, $fallbackLocales);

        return new DoctrineMessageCatalogueProvider($resourceCatalogue, $this->cache, $debug);
    }
}
