<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Translation;

use Symfony\Bridge\Doctrine\Translation\DoctrineMessageCatalogue;
use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Translation\Tests\MessageCatalogueTest;

class DoctrineMessageCatalogueTest extends MessageCatalogueTest
{
    protected function setUp()
    {
        if (!interface_exists('Doctrine\Common\Cache\Cache')) {
            $this->markTestSkipped('The "Doctrine Cache" is not available');
        }
    }

    public function testAll()
    {
        if (!interface_exists('Doctrine\Common\Cache\MultiGetCache')) {
            $this->markTestSkipped('The "Doctrine MultiGetCache" is not available');
        }

        parent::testAll();
    }

    protected function getCatalogue($locale, $messages = array())
    {
        $catalogue = new DoctrineMessageCatalogue($locale, new ArrayCache());
        foreach ($messages as $domain => $domainMessages) {
            $catalogue->add($domainMessages, $domain);
        }

        return $catalogue;
    }
}
