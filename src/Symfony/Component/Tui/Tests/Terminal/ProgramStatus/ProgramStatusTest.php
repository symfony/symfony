<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Terminal\ProgramStatus;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Terminal\ProgramStatus\ProgramBlockKind;
use Symfony\Component\Tui\Terminal\ProgramStatus\ProgramState;
use Symfony\Component\Tui\Terminal\ProgramStatus\ProgramStatus;

class ProgramStatusTest extends TestCase
{
    public function testEveryKey()
    {
        $status = new ProgramStatus(ProgramState::Blocked, 'Approve deploy?', 'deploy', ProgramBlockKind::Permission, 40, 'deploy/eu-west', 'EU West');

        $this->assertSame("\x1b]7501;state=blocked:id=deploy/eu-west:kind=permission:progress=40:app=deploy:title=".base64_encode('EU West').':msg='.base64_encode('Approve deploy?')."\x1b\\", $status->toEscapeSequence());
    }

    public function testTheLargestReportFitsTheSequenceLimit()
    {
        $id = implode('/', array_fill(0, 3, str_repeat('a', 32))).'/'.str_repeat('b', 29);
        $status = new ProgramStatus(ProgramState::Blocked, str_repeat('m', 3000), str_repeat('a', 32), ProgramBlockKind::Permission, 100, $id, str_repeat('t', 300));

        $this->assertSame(2732, \strlen(base64_encode((string) $status->message)));
        $this->assertSame(256, \strlen(base64_encode((string) $status->title)));
        $this->assertLessThanOrEqual(4096, \strlen($status->toEscapeSequence()));
    }

    #[DataProvider('provideInvalidIds')]
    public function testInvalidId(string $id)
    {
        $this->expectException(InvalidArgumentException::class);

        new ProgramStatus(ProgramState::Working, id: $id);
    }

    public static function provideInvalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'empty segment' => ['build//test'];
        yield 'separator' => ['build:test'];
        yield 'trailing newline' => ["build\n"];
        yield 'segment too long' => [str_repeat('a', 33)];
        yield 'too deep' => [implode('/', range(1, 9))];
        yield 'too long' => [implode('/', array_fill(0, 4, str_repeat('a', 32)))];
    }

    #[DataProvider('provideInvalidIds')]
    public function testInvalidIdIsRejectedWhenClearing(string $id)
    {
        $this->expectException(InvalidArgumentException::class);

        ProgramStatus::toClearEscapeSequence($id);
    }

    public function testDeepestValidId()
    {
        $this->assertSame('1/2/3/4/5/6/7/8', (new ProgramStatus(ProgramState::Idle, id: '1/2/3/4/5/6/7/8'))->id);
    }

    public function testControlCharactersInTheMessageAreReplacedBySpaces()
    {
        $status = new ProgramStatus(ProgramState::Error, "rsync failed:\n\tdisk\x1b[31m full\xc2\x9b\x7f");

        $this->assertSame('rsync failed: disk full', $status->message);
    }

    public function testControlCharactersInTheTitleAreReplacedBySpaces()
    {
        $status = new ProgramStatus(ProgramState::Working, null, null, null, null, null, "EU\nWest\x1b");

        $this->assertSame('EU West', $status->title);
    }

    public function testInvalidUtf8InTheMessageIsScrubbed()
    {
        $status = new ProgramStatus(ProgramState::Done, "caf\xe9");

        $this->assertTrue(mb_check_encoding((string) $status->message, 'UTF-8'));
    }

    public function testBlankMessageIsOmitted()
    {
        $status = new ProgramStatus(ProgramState::Done, " \n ");

        $this->assertNull($status->message);
        $this->assertSame("\x1b]7501;state=done\x1b\\", $status->toEscapeSequence());
    }

    public function testLongMessageIsCutOnACharacterBoundary()
    {
        $status = new ProgramStatus(ProgramState::Working, 'a'.str_repeat('é', 1100));

        $this->assertSame('a'.str_repeat('é', 1023), $status->message);
    }

    #[DataProvider('provideInvalidApps')]
    public function testInvalidApp(string $app)
    {
        $this->expectException(InvalidArgumentException::class);

        new ProgramStatus(ProgramState::Working, app: $app);
    }

    public static function provideInvalidApps(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'separator' => ['my:app'];
        yield 'trailing newline' => ["cargo\n"];
    }

    public function testKindRequiresTheBlockedState()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A program status kind is only supported for the "blocked" state, "working" given.');

        new ProgramStatus(ProgramState::Working, kind: ProgramBlockKind::Question);
    }

    public function testProgressRequiresAWorkingOrBlockedState()
    {
        $this->expectException(InvalidArgumentException::class);

        new ProgramStatus(ProgramState::Done, progress: 50);
    }

    #[DataProvider('provideOutOfRangeProgress')]
    public function testProgressMustBeAPercentage(int $progress)
    {
        $this->expectException(InvalidArgumentException::class);

        new ProgramStatus(ProgramState::Working, progress: $progress);
    }

    public static function provideOutOfRangeProgress(): iterable
    {
        yield [-1];
        yield [101];
    }
}
