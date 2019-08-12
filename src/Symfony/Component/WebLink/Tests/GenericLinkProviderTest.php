<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * Test case borrowed from https://github.com/php-fig/link/.
 */
class GenericLinkProviderTest extends TestCase
{
    public function testCanAddLinksByMethod()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $provider = (new GenericLinkProvider())
            ->withLink($link);

        $this->assertContains($link, $provider->getLinks());
    }

    public function testCanAddLinksByConstructor()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $provider = (new GenericLinkProvider())
            ->withLink($link);

        $this->assertContains($link, $provider->getLinks());
    }

    public function testCanGetLinksByRel()
    {
        $link1 = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;
        $link2 = (new Link())
            ->withHref('http://www.php-fig.org/')
            ->withRel('home')
            ->withAttribute('me', 'you')
        ;

        $provider = (new GenericLinkProvider())
            ->withLink($link1)
            ->withLink($link2);

        $links = $provider->getLinksByRel('home');
        $this->assertContains($link2, $links);
        $this->assertNotContains($link1, $links);
    }

    public function testCanRemoveLinks()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $provider = (new GenericLinkProvider())
            ->withLink($link)
            ->withoutLink($link);

        $this->assertNotContains($link, $provider->getLinks());
    }
}
