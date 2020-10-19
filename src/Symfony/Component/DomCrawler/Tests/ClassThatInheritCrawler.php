<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use Symfony\Component\DomCrawler\Crawler;

class ClassThatInheritCrawler extends Crawler
{
    /**
     * @return static
     */
    public function children()
    {
        return parent::children();
    }
}
