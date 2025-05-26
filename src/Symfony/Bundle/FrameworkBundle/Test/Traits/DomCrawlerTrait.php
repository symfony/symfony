<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Component\DomCrawler\Crawler;

trait DomCrawlerTrait
{
    private static function getCrawler(): Crawler
    {
        trigger_deprecation('symfony/framework-bundle', '7.4', 'Use getClientCrawler() instead.');
        return self::getClientCrawler();
    }

    public static function getClientCrawler(): Crawler
    {
        if (!$crawler = self::getClient()->getCrawler()) {
            static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
        }

        return $crawler;
    }
}
