<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Tests;

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form as DomCrawlerForm;

class ClassThatInheritClient extends AbstractBrowser
{
    protected $nextResponse = null;

    public function setNextResponse(Response $response)
    {
        $this->nextResponse = $response;
    }

    protected function doRequest($request): Response
    {
        if (null === $this->nextResponse) {
            return new Response();
        }

        $response = $this->nextResponse;
        $this->nextResponse = null;

        return $response;
    }

    /**
     * @param array $serverParameters
     */
    public function submit(DomCrawlerForm $form, array $values = []/*, array $serverParameters = []*/): Crawler
    {
        return parent::submit($form, $values);
    }
}
