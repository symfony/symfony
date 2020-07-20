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

trait WebTestAssertionsTrait
{
    use BrowserKitAssertionsTrait;
    use DomCrawlerAssertionsTrait;
}
