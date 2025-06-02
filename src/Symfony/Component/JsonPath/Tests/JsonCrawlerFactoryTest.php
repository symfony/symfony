<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\JsonCrawlerFactory;

class JsonCrawlerFactoryTest extends TestCase
{
    public function testCreateCrawler()
    {
        $factory = new JsonCrawlerFactory();
        $crawler = $factory->createFromData('{"foo": "bar"}');

        $this->assertSame(['bar'], $crawler->find('$.foo'), '->createFromData() provides data to the crawler');
    }
}
