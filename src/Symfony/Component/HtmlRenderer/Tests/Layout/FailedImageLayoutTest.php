<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class FailedImageLayoutTest extends TestCase
{
    public function testPresentationalDimensionsRemainWhenImageCannotBeDecoded()
    {
        foreach (['', 'missing'] as $alternative) {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><img src="data:image/png;base64,aW52YWxpZA==" width="30" height="20" alt="'.$alternative.'" style="display:inline-block;border:2px solid">');
            $image = $document->querySelector('img');
            $result = (new HtmlRenderer())->render($document, new RenderingContext(300, 200));
            $bounds = $result->getBounds($image)[0];
            self::assertSame(34.0, $bounds->width);
            self::assertSame(24.0, $bounds->height);
            self::assertSame($alternative, $image->getAttribute('alt'));
        }
    }

    public function testAuthorDimensionsOverrideFailedImageAttributes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><img src="data:image/png;base64,aW52YWxpZA==" width="30" height="20" alt="" style="display:inline-block;width:12.5px;height:7.25px">');
        $image = $document->querySelector('img');
        $bounds = (new HtmlRenderer())->render($document, new RenderingContext(300, 200))->getBounds($image)[0];
        self::assertSame(12.5, $bounds->width);
        self::assertSame(7.25, $bounds->height);
    }
}
