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

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Bridge\Doctrine\Translation\DoctrineMessageCache;
use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
use Symfony\Component\Translation\Tests\TranslatorCacheTest;

class TranslatorDoctrineCacheTest extends TranslatorCacheTest
{
    private $cache;

    protected function setUp()
    {
        if (!interface_exists('Doctrine\Common\Cache\Cache')) {
            $this->markTestSkipped('The "Doctrine Cache" is not available');
        }

        $this->cache = new ArrayCache();
    }

    protected function getTranslator($locale, $debug)
    {
        $cache = new DoctrineMessageCache($this->cache, $debug);

        return new Translator($locale, null, $cache);
    }
}
