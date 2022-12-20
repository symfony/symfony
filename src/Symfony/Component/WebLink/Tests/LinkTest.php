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
use Symfony\Component\WebLink\Link;

/**
 * Test case borrowed from https://github.com/php-fig/link/.
 */
class LinkTest extends TestCase
{
    public function testCanSetAndRetrieveValues()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        self::assertEquals('http://www.google.com', $link->getHref());
        self::assertContains('next', $link->getRels());
        self::assertArrayHasKey('me', $link->getAttributes());
        self::assertEquals('you', $link->getAttributes()['me']);
    }

    public function testCanRemoveValues()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $link = $link->withoutAttribute('me')
            ->withoutRel('next');

        self::assertEquals('http://www.google.com', $link->getHref());
        self::assertFalse(\in_array('next', $link->getRels()));
        self::assertArrayNotHasKey('me', $link->getAttributes());
    }

    public function testMultipleRels()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withRel('reference');

        self::assertCount(2, $link->getRels());
        self::assertContains('next', $link->getRels());
        self::assertContains('reference', $link->getRels());
    }

    public function testConstructor()
    {
        $link = new Link('next', 'http://www.google.com');

        self::assertEquals('http://www.google.com', $link->getHref());
        self::assertContains('next', $link->getRels());
    }

    /**
     * @dataProvider templatedHrefProvider
     */
    public function testTemplated(string $href)
    {
        $link = (new Link())
            ->withHref($href);

        self::assertTrue($link->isTemplated());
    }

    /**
     * @dataProvider notTemplatedHrefProvider
     */
    public function testNotTemplated(string $href)
    {
        $link = (new Link())
            ->withHref($href);

        self::assertFalse($link->isTemplated());
    }

    public function templatedHrefProvider()
    {
        return [
            ['http://www.google.com/{param}/foo'],
            ['http://www.google.com/foo?q={param}'],
        ];
    }

    public function notTemplatedHrefProvider()
    {
        return [
            ['http://www.google.com/foo'],
            ['/foo/bar/baz'],
        ];
    }
}
