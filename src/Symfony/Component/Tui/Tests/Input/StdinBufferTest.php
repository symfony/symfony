<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Tui\Event\PasteCompletedEvent;
use Symfony\Component\Tui\Event\PasteStartedEvent;
use Symfony\Component\Tui\Input\StdinBuffer;

class StdinBufferTest extends TestCase
{
    /**
     * @param string[] $expectedSequences
     */
    #[DataProvider('sequenceProvider')]
    public function testProcess(string $input, array $expectedSequences)
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        $buffer->process($input);

        $this->assertSame($expectedSequences, $sequences);
    }

    /**
     * @return array<string, array{string, string[]}>
     */
    public static function sequenceProvider(): array
    {
        return [
            'single escape' => [
                "\x1b",
                [], // Incomplete, waiting for more
            ],
            'double escape' => [
                "\x1b\x1b",
                [], // Need to wait for third char to decide
            ],
            'escape then down arrow' => [
                "\x1b\x1b[B",
                ['1b', '1b5b42'],
            ],
            'escape then up arrow' => [
                "\x1b\x1b[A",
                ['1b', '1b5b41'],
            ],
            'escape then SS3 sequence' => [
                "\x1b\x1bOP",
                ['1b', '1b4f50'],
            ],
            'down arrow' => [
                "\x1b[B",
                ['1b5b42'],
            ],
            'up arrow' => [
                "\x1b[A",
                ['1b5b41'],
            ],
            'multiple arrows' => [
                "\x1b[A\x1b[B\x1b[C",
                ['1b5b41', '1b5b42', '1b5b43'],
            ],
            'escape then multiple arrows' => [
                "\x1b\x1b[A\x1b[B",
                ['1b', '1b5b41', '1b5b42'],
            ],
            'plain text' => [
                'hello',
                ['68', '65', '6c', '6c', '6f'],
            ],
            'enter key' => [
                "\r",
                ['0d'],
            ],
            'alt+a' => [
                "\x1ba",
                ['1b61'],
            ],
            'SS3 F1' => [
                "\x1bOP",
                ['1b4f50'],
            ],
            'CSI with params' => [
                "\x1b[1;5A",
                ['1b5b313b3541'],
            ],
            'double escape then letter' => [
                "\x1b\x1ba",
                ['1b1b', '61'], // Double-escape then 'a'
            ],
            'triple escape then arrow' => [
                "\x1b\x1b\x1b[A",
                ['1b1b', '1b5b41'], // First two ESC are double-escape, then ESC+arrow
            ],
            'alt+backspace (DEL)' => [
                "\x1b\x7f",
                ['1b7f'],
            ],
            'alt+backspace (BS)' => [
                "\x1b\x08",
                ['1b08'],
            ],
            'alt+space' => [
                "\x1b\x20",
                ['1b20'],
            ],
            'alt+enter' => [
                "\x1b\r",
                ['1b0d'],
            ],
            'ctrl+alt+]' => [
                "\x1b\x1d",
                ['1b1d'],
            ],
            'ctrl+alt+\\' => [
                "\x1b\x1c",
                ['1b1c'],
            ],
            'ctrl+alt+-' => [
                "\x1b\x1f",
                ['1b1f'],
            ],
        ];
    }

    /**
     * @param string[] $expectedSequences
     */
    #[DataProvider('highByteMetaProvider')]
    public function testHighByteMetaConversion(string $input, array $expectedSequences)
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        $buffer->process($input);

        $this->assertSame($expectedSequences, $sequences);
    }

    /**
     * @return array<string, array{string, string[]}>
     */
    public static function highByteMetaProvider(): array
    {
        return [
            'high-byte alt+backspace (0xFF)' => [
                "\xFF",
                ['1b7f'], // Converted to ESC + DEL
            ],
            'high-byte alt+d (0xE4)' => [
                "\xE4",
                ['1b64'], // Converted to ESC + d
            ],
            'high-byte alt+space (0xA0)' => [
                "\xA0",
                ['1b20'], // Converted to ESC + space
            ],
            'multi-byte UTF-8 not converted' => [
                "\xC3\xA9", // é in UTF-8
                ['c3a9'], // Preserved as-is
            ],
        ];
    }

    public function testBracketedPaste()
    {
        $buffer = new StdinBuffer();
        $sequences = [];
        $pastedContent = null;

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });

        $buffer->onPaste(static function (string $content) use (&$pastedContent) {
            $pastedContent = $content;
        });

        $buffer->process("\x1b[200~Hello World\x1b[201~");

        $this->assertSame([], $sequences);
        $this->assertSame('Hello World', $pastedContent);
    }

    public function testIncrementalInput()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        // Send escape alone
        $buffer->process("\x1b");
        $this->assertSame([], $sequences, 'Escape alone should wait');

        // Now send the rest of the arrow sequence
        $buffer->process('[A');
        $this->assertSame(['1b5b41'], $sequences);
    }

    public function testEscapeThenArrowIncremental()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        // Send escape alone - should wait
        $buffer->process("\x1b");
        $this->assertSame([], $sequences, 'Single escape should wait');

        // Send the CSI sequence - now we have ESC + ESC[B
        // But actually we're sending ESC then [B separately
        $buffer->process("\x1b[B");

        // The buffer should see \x1b\x1b[B and parse as ESC then Down
        $this->assertSame(['1b', '1b5b42'], $sequences);
    }

    public function testFlushEmitsStandaloneEscape()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        $buffer->process("\x1b");
        $this->assertSame([], $sequences);

        $buffer->flush();
        $this->assertSame(['1b'], $sequences);
        $this->assertSame('', $buffer->getBuffer());
    }

    public function testFlushDoesNothingWithoutPendingEscape()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });

        $buffer->process('a');
        $buffer->flush();

        $this->assertSame(['a'], $sequences);
    }

    public function testClearResetsAllState()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });

        // Start a paste and leave it incomplete
        $buffer->process("\x1b[200~partial");
        $buffer->clear();

        $this->assertSame('', $buffer->getBuffer());

        // After clear, should process normally again
        $buffer->process('a');
        $this->assertSame(['a'], $sequences);
    }

    #[DataProvider('singleSequenceProvider')]
    public function testSingleSequence(string $input)
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        $buffer->process($input);

        $this->assertCount(1, $sequences);
        $this->assertSame(bin2hex($input), $sequences[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function singleSequenceProvider(): iterable
    {
        yield 'OSC with BEL terminator' => ["\x1b]0;My Title\x07"];
        yield 'OSC with ST terminator' => ["\x1b]0;Title\x1b\\"];
        yield 'DCS sequence' => ["\x1bPq#0;2;0;0;0\x1b\\"];
        yield 'APC sequence' => ["\x1b_G;data\x07"];
        yield 'old-style mouse' => ["\x1b[M !\""];
        yield 'SGR mouse' => ["\x1b[<0;10;20M"];
    }

    public function testIncrementalPaste()
    {
        $buffer = new StdinBuffer();
        $pasteCount = 0;
        $pastedContent = null;

        $buffer->onPaste(static function (string $content) use (&$pastedContent, &$pasteCount) {
            $pastedContent = $content;
            ++$pasteCount;
        });

        // Send paste start
        $buffer->process("\x1b[200~Hello");
        $this->assertSame(0, $pasteCount, 'Paste should not fire until end marker');

        // Send more paste content
        $buffer->process(' World');
        $this->assertSame(0, $pasteCount, 'Paste should still not fire');

        // Send paste end
        $buffer->process("\x1b[201~");
        $this->assertSame(1, $pasteCount);
        $this->assertSame('Hello World', $pastedContent);
    }

    public function testPasteLifecycleEventsAreDispatched()
    {
        $dispatcher = new EventDispatcher();
        $buffer = new StdinBuffer($dispatcher);
        $events = [];
        $pastedContent = null;

        $dispatcher->addListener(PasteStartedEvent::class, static function () use (&$events) {
            $events[] = 'started';
        });
        $dispatcher->addListener(PasteCompletedEvent::class, static function () use (&$events) {
            $events[] = 'completed';
        });
        $buffer->onPaste(static function (string $content) use (&$pastedContent) {
            $pastedContent = $content;
        });

        $buffer->process("\x1b[200~Hello");

        $this->assertSame(['started'], $events);
        $this->assertNull($pastedContent);

        $buffer->process(" World\x1b[201~");

        $this->assertSame(['started', 'completed'], $events);
        $this->assertSame('Hello World', $pastedContent);
    }

    public function testPasteLifecycleEventsAreDispatchedOnOverflow()
    {
        $dispatcher = new EventDispatcher();
        $buffer = new StdinBuffer($dispatcher);
        $events = [];

        $dispatcher->addListener(PasteStartedEvent::class, static function () use (&$events) {
            $events[] = 'started';
        });
        $dispatcher->addListener(PasteCompletedEvent::class, static function () use (&$events) {
            $events[] = 'completed';
        });
        $buffer->onData(static function (string $data) {});
        $buffer->onPaste(static function (string $content) {});

        $buffer->process("\x1b[200~");
        $buffer->process(str_repeat('A', 17 * 1024 * 1024));

        $this->assertSame(['started'], $events);

        $buffer->process("\x1b[201~");

        $this->assertSame(['started', 'completed'], $events);
    }

    public function testClearMidPasteDispatchesPasteCompleted()
    {
        $dispatcher = new EventDispatcher();
        $buffer = new StdinBuffer($dispatcher);
        $events = [];
        $pastes = [];

        $dispatcher->addListener(PasteStartedEvent::class, static function () use (&$events) {
            $events[] = 'started';
        });
        $dispatcher->addListener(PasteCompletedEvent::class, static function () use (&$events) {
            $events[] = 'completed';
        });
        $buffer->onPaste(static function (string $content) use (&$pastes) {
            $pastes[] = $content;
        });

        $buffer->process("\x1b[200~partial");
        $buffer->clear();

        $this->assertSame(['started', 'completed'], $events);
        $this->assertSame([], $pastes, 'A discarded paste must not reach the paste callback');

        $buffer->clear();

        $this->assertSame(['started', 'completed'], $events, 'Clearing outside a paste must not dispatch events');
    }

    public function testDataAfterPasteEndIsProcessed()
    {
        $buffer = new StdinBuffer();
        $sequences = [];
        $pastedContent = null;

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });
        $buffer->onPaste(static function (string $content) use (&$pastedContent) {
            $pastedContent = $content;
        });

        // Paste followed by a normal key
        $buffer->process("\x1b[200~text\x1b[201~a");

        $this->assertSame('text', $pastedContent);
        $this->assertSame(['a'], $sequences);
    }

    public function testUtf8MultiByte()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });

        // 2-byte: é (C3 A9), 3-byte: € (E2 82 AC), 4-byte: 🎉 (F0 9F 8E 89)
        $buffer->process('é€🎉');

        $this->assertSame(['é', '€', '🎉'], $sequences);
    }

    public function testIncompleteUtf8WaitsForMoreData()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = $data;
        });

        // Send 3-byte UTF-8 € (E2 82 AC) split: first byte arrives with a prefix
        // to avoid high-byte meta conversion (which triggers when strlen==1)
        $buffer->process("a\xE2");
        // 'a' emitted immediately, \xE2 waits (need 2 more bytes)
        $this->assertSame(['a'], $sequences, 'Only ASCII should emit, incomplete UTF-8 should wait');

        // Send remaining bytes
        $buffer->process("\x82\xAC");
        $this->assertSame(['a', '€'], $sequences);
    }

    public function testIncompleteCsiWaitsForTerminator()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) {
            $sequences[] = bin2hex($data);
        });

        // Send CSI without terminator
        $buffer->process("\x1b[1;5");
        $this->assertSame([], $sequences, 'Incomplete CSI should wait');

        // Send terminator
        $buffer->process('A');
        $this->assertSame([bin2hex("\x1b[1;5A")], $sequences);
    }

    public function testUnterminatedPasteAbortsAtCap()
    {
        $buffer = new StdinBuffer();
        $sequences = [];
        $pastes = [];

        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });
        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~");
        $buffer->process(str_repeat('A', 17 * 1024 * 1024));

        $this->assertSame([], $sequences);
        $this->assertSame(['[paste exceeded 16 MiB limit]'], $pastes);

        // The terminal is still sending the paste, so what follows is pasted
        // content and not typing.
        $buffer->process("rm -rf /tmp/x\r");

        $this->assertSame([], $sequences, 'Content sent after the abort is dropped, not delivered as keys');
        $this->assertSame(['[paste exceeded 16 MiB limit]'], $pastes, 'The overflow notice is sent once');
    }

    public function testInputResumesOnceAnAbortedPasteEnds()
    {
        $buffer = new StdinBuffer();
        $sequences = [];
        $pastes = [];

        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });
        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~");
        $buffer->process(str_repeat('A', 17 * 1024 * 1024));
        $buffer->process("tail\x1b");
        $buffer->process('[201~');

        $this->assertSame([], $sequences, 'The end marker is not delivered as a key');
        $this->assertSame(['[paste exceeded 16 MiB limit]'], $pastes);

        $buffer->process('B');
        $buffer->process("\x1b[200~next\x1b[201~");

        $this->assertSame(['B'], $sequences);
        $this->assertSame(['[paste exceeded 16 MiB limit]', 'next'], $pastes);
    }

    public function testFlushKeepsAHeldEndMarkerWhileAPasteIsOpen()
    {
        $buffer = new StdinBuffer();
        $sequences = [];
        $pastes = [];

        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });
        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        // A read can end on the ESC of the end marker. Emitting it as the
        // Escape key would leave the paste open with no way to close it.
        $buffer->process("\x1b[200~AA\x1b");
        $buffer->flush();
        $buffer->process('[201~x');
        $buffer->flush();

        $this->assertSame(['AA'], $pastes);
        $this->assertSame(['x'], $sequences);
    }

    public function testUnterminatedPasteAbortsAtCapWhenEveryWriteEndsWithAPartialEndMarker()
    {
        $buffer = new StdinBuffer();
        $pastes = [];

        $buffer->onData(static function (string $data) {});
        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~");

        // A lone ESC is a one-byte prefix of "\x1b[201~", so it gets held back
        // for the next read instead of being appended to the content. The cap
        // has to be enforced on that path too, or a writer that ends every
        // chunk this way never reaches it and the buffer grows unbounded.
        for ($i = 0; $i < 24 && !$pastes; ++$i) {
            $buffer->process(str_repeat('A', 1024 * 1024)."\x1b");
        }

        $this->assertSame(['[paste exceeded 16 MiB limit]'], $pastes);
    }

    public function testUnterminatedOscAbortsAtCap()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });

        $buffer->process("\x1b]0;");
        $buffer->process(str_repeat('A', 2 * 1024 * 1024));

        $this->assertSame([], $sequences);

        $buffer->process('B');
        $this->assertSame(['B'], $sequences, 'Buffer should accept new input after abort');
    }

    public function testMalformedSgrMouseSequenceDoesNotBlockLaterInput()
    {
        $buffer = new StdinBuffer();
        $sequences = [];

        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });

        // Four parameters instead of three: the terminator is already there, so
        // more data can never make this sequence valid.
        $buffer->process("\x1b[<3;1;1;1M");
        $buffer->process('ab');

        $this->assertSame(["\x1b[<3;1;1;1M", 'a', 'b'], $sequences);
        $this->assertSame('', $buffer->getBuffer());
    }

    public function testNestedPasteStartMarkerIsKeptAsPastedContent()
    {
        $buffer = new StdinBuffer();
        $pastes = [];

        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~AAA");
        $buffer->process("\x1b[200~BBB\x1b[201~");

        $this->assertSame(["AAA\x1b[200~BBB"], $pastes);
    }

    public function testNestedPasteStartMarkerInASingleReadIsKeptAsPastedContent()
    {
        $buffer = new StdinBuffer();
        $pastes = [];

        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~AAA\x1b[200~BBB\x1b[201~");

        $this->assertSame(["AAA\x1b[200~BBB"], $pastes);
    }

    #[DataProvider('provideSplitPasteEndMarkers')]
    public function testPasteEndMarkerSplitAcrossReadsStillClosesThePaste(string $first, string $second)
    {
        $buffer = new StdinBuffer();
        $pastes = [];
        $sequences = [];

        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });
        $buffer->onData(static function (string $data) use (&$sequences) { $sequences[] = $data; });

        $buffer->process($first);
        $buffer->process($second);
        $buffer->process('x');

        $this->assertSame(['AABB'], $pastes);
        $this->assertSame(['x'], $sequences, 'Buffer should accept new input after the paste');
    }

    public static function provideSplitPasteEndMarkers(): array
    {
        return [
            'split after ESC' => ["\x1b[200~AABB\x1b", '[201~'],
            'split mid marker' => ["\x1b[200~AABB\x1b[2", '01~'],
            'split before terminator' => ["\x1b[200~AABB\x1b[201", '~'],
        ];
    }

    public function testPasteContentThatLooksLikeAPartialEndMarkerIsKept()
    {
        $buffer = new StdinBuffer();
        $pastes = [];

        $buffer->onPaste(static function (string $data) use (&$pastes) { $pastes[] = $data; });

        $buffer->process("\x1b[200~AA\x1b[2");
        $buffer->process("XX\x1b[201~");

        $this->assertSame(["AA\x1b[2XX"], $pastes);
    }
}
