<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\BracketedPasteTrait;

class BracketedPasteHandler
{
    use BracketedPasteTrait {
        processBracketedPaste as public;
        isBufferingPaste as public;
    }
}

class BracketedPasteTraitTest extends TestCase
{
    public function testSingleChunkPaste()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~hello world\x1b[201~";
        $result = $handler->processBracketedPaste($data);

        $this->assertSame('hello world', $result);
        $this->assertSame('', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public function testMultiChunkPaste()
    {
        $handler = $this->createHandler();

        // First chunk: start marker + partial content
        $data = "\x1b[200~hello ";
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result);
        $this->assertSame('', $data);
        $this->assertTrue($handler->isBufferingPaste());

        // Second chunk: more content
        $data = 'world';
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result);
        $this->assertSame('', $data);
        $this->assertTrue($handler->isBufferingPaste());

        // Third chunk: end marker
        $data = "!\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('hello world!', $result);
        $this->assertSame('', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public function testDataAfterEndMarkerIsPreserved()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~pasted\x1b[201~extra input";
        $result = $handler->processBracketedPaste($data);

        $this->assertSame('pasted', $result);
        $this->assertSame('extra input', $data);
    }

    public function testDataBeforeStartMarkerIsPreserved()
    {
        $handler = $this->createHandler();

        $data = "AAA\x1b[200~pasted\x1b[201~BBB";
        $result = $handler->processBracketedPaste($data);

        $this->assertSame('pasted', $result);
        $this->assertSame('AAABBB', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public function testDataBeforeStartMarkerWithoutEndIsPreservedAsPrefix()
    {
        $handler = $this->createHandler();

        $data = "AAA\x1b[200~partial";
        $result = $handler->processBracketedPaste($data);

        $this->assertNull($result);
        $this->assertSame('AAA', $data);
        $this->assertTrue($handler->isBufferingPaste());

        $data = "rest\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('partialrest', $result);
        $this->assertSame('', $data);
    }

    public function testNoPasteMarkers()
    {
        $handler = $this->createHandler();

        $data = 'regular input';
        $result = $handler->processBracketedPaste($data);

        $this->assertNull($result);
        $this->assertSame('regular input', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public function testEmptyPaste()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~\x1b[201~";
        $result = $handler->processBracketedPaste($data);

        $this->assertSame('', $result);
        $this->assertSame('', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public function testPasteWithNewlines()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~line1\nline2\nline3\x1b[201~";
        $result = $handler->processBracketedPaste($data);

        $this->assertSame("line1\nline2\nline3", $result);
        $this->assertSame('', $data);
    }

    public function testBufferingClearsDataWhileInPaste()
    {
        $handler = $this->createHandler();

        // Start paste
        $data = "\x1b[200~partial";
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result);
        $this->assertSame('', $data);

        // Still buffering - data should be emptied
        $data = ' more content';
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result);
        $this->assertSame('', $data);

        // End paste
        $data = " end\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('partial more content end', $result);
    }

    public function testConsecutivePastes()
    {
        $handler = $this->createHandler();

        // First paste
        $data = "\x1b[200~first\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('first', $result);

        // Second paste
        $data = "\x1b[200~second\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('second', $result);
    }

    public function testUnterminatedPasteAbortsAtCap()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~";
        $handler->processBracketedPaste($data);
        $this->assertTrue($handler->isBufferingPaste());

        $data = str_repeat('A', 17 * 1024 * 1024);
        $result = $handler->processBracketedPaste($data);

        $this->assertSame('[paste exceeded 16 MiB limit]', $result);
        $this->assertSame('', $data);
        $this->assertTrue($handler->isBufferingPaste(), 'The paste stays open until the end marker');

        // The terminal is still sending the paste, so what follows is pasted
        // content and not typing.
        $data = 'plain';
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result);
        $this->assertSame('', $data);

        $data = "more\x1b[201~after";
        $result = $handler->processBracketedPaste($data);
        $this->assertNull($result, 'The dropped content is not returned');
        $this->assertSame('after', $data);
        $this->assertFalse($handler->isBufferingPaste());

        $data = "\x1b[200~next\x1b[201~";
        $result = $handler->processBracketedPaste($data);
        $this->assertSame('next', $result, 'The next paste is delivered');
    }

    public function testPartialPasteEndMarkerIsKeptAsContent()
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~AA\x1b[2";
        $this->assertNull($handler->processBracketedPaste($data));

        $data = "XX\x1b[201~";
        $this->assertSame("AA\x1b[2XX", $handler->processBracketedPaste($data));
    }

    #[DataProvider('provideSplitPasteEndMarkers')]
    public function testOverflowedPasteEndMarkerCanBeSplitAcrossChunks(string $first, string $second)
    {
        $handler = $this->createHandler();

        $data = "\x1b[200~";
        $handler->processBracketedPaste($data);

        $data = str_repeat('A', 17 * 1024 * 1024);
        $this->assertSame('[paste exceeded 16 MiB limit]', $handler->processBracketedPaste($data));

        $data = 'tail'.$first;
        $this->assertNull($handler->processBracketedPaste($data));
        $this->assertSame('', $data);

        $data = $second.'after';
        $this->assertNull($handler->processBracketedPaste($data));
        $this->assertSame('after', $data);
        $this->assertFalse($handler->isBufferingPaste());
    }

    public static function provideSplitPasteEndMarkers(): iterable
    {
        $marker = "\x1b[201~";

        for ($i = 1; $i < \strlen($marker); ++$i) {
            yield "split after {$i} byte(s)" => [substr($marker, 0, $i), substr($marker, $i)];
        }
    }

    private function createHandler(): BracketedPasteHandler
    {
        return new BracketedPasteHandler();
    }
}
