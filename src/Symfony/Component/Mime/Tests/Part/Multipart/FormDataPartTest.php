<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\TextPart;

class FormDataPartTest extends TestCase
{
    public function testConstructor()
    {
        $b = new TextPart('content');
        $c = DataPart::fromPath($file = __DIR__.'/../../Fixtures/mimetypes/test.gif');
        $f = new FormDataPart([
            'foo' => $content = 'very very long content that will not be cut even if the length i way more than 76 characters, ok?',
            'bar' => clone $b,
            'baz' => clone $c,
        ]);
        $this->assertEquals('multipart', $f->getMediaType());
        $this->assertEquals('form-data', $f->getMediaSubtype());
        $t = new TextPart($content);
        $t->setDisposition('form-data');
        $t->setName('foo');
        $t->getHeaders()->setMaxLineLength(1000);
        $b->setDisposition('form-data');
        $b->setName('bar');
        $b->getHeaders()->setMaxLineLength(1000);
        $c->setDisposition('form-data');
        $c->setName('baz');
        $c->getHeaders()->setMaxLineLength(1000);
        $this->assertEquals([$t, $b, $c], $f->getParts());
    }
}
