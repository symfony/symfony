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

trigger_deprecation('symfony/framework-bundle', '7.4', 'Use Traits\<type> instead.');
trait WebTestAssertionsTrait
{
    use Traits\BrowserKitTrait;
    use Traits\BrowserKitAssertionsTrait;
    use Traits\DomCrawlerTrait;
    use Traits\DomCrawlerAssertionsTrait;
    use Traits\HttpClientTrait;
    use Traits\HttpClientAssertionsTrait;
}
