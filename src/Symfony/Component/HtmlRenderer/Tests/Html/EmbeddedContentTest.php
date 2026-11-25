<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Html\EmbeddedContent;

final class EmbeddedContentTest extends TestCase
{
    public function testAttributePrecedenceAndEmptyAlternativesArePreserved()
    {
        $document = \Dom\HTMLDocument::createFromString('<img alt="Image" title="Other"><object aria-label="Label" title="Title"><title>Child</title></object>', \LIBXML_NOERROR);
        self::assertSame('Image', EmbeddedContent::alternative($document->querySelector('img')));
        $object = $document->querySelector('object');
        self::assertSame('Label', EmbeddedContent::alternative($object));
        $object->setAttribute('aria-label', '');
        self::assertSame('', EmbeddedContent::alternative($object));
        $object->removeAttribute('aria-label');
        self::assertSame('Title', EmbeddedContent::alternative($object));
        $object->removeAttribute('title');
        self::assertSame('Child', EmbeddedContent::alternative($object));
        $object->setAttribute('aria-hidden', ' TRUE ');
        self::assertSame('', EmbeddedContent::alternative($object));
    }
}
