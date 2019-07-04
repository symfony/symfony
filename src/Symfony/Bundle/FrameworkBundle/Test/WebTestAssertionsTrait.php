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

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait WebTestAssertionsTrait
{
    use BrowserKitAssertionsTrait;
    use DomCrawlerAssertionsTrait;
}
