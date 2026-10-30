<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Terminal\ProgramStatus;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * A program status report, as defined by the Program Status Protocol (OSC 7501).
 *
 * The terminal decides how to present it: a tab indicator, a notification, an
 * inbox, etc.
 *
 * A report without an id describes the program itself. A program running
 * several tasks, like workers or sub-agents, can describe each of them with
 * its own id; a "/" in an id makes "build/test" a child of "build".
 *
 * A report replaces the previous one with the same id as a whole, so each
 * report repeats the app and title it wants to keep.
 *
 * @see https://www.superlogical.com/rex/docs/build/program-status
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ProgramStatus
{
    private const string ID_PATTERN = '/^[A-Za-z0-9_.+-]{1,32}(?:\/[A-Za-z0-9_.+-]{1,32}){0,7}$/D';
    private const int MAX_ID_BYTES = 128;
    private const int MAX_MESSAGE_BYTES = 2048;
    private const int MAX_TITLE_BYTES = 192;

    public readonly ?string $message;
    public readonly ?string $title;

    /**
     * @param string|null           $message  One human-readable line; control characters are replaced by spaces and long text is cut
     * @param string|null           $app      A stable identifier for the program, like "cargo" or "terraform"
     * @param ProgramBlockKind|null $kind     What a blocked program waits for
     * @param int|null              $progress A percentage for a working or blocked program, null when indeterminate
     * @param string|null           $id       The task the report is about, null for the program itself
     * @param string|null           $title    A short label for the task; control characters are replaced by spaces and long text is cut
     */
    public function __construct(
        public readonly ProgramState $state,
        ?string $message = null,
        public readonly ?string $app = null,
        public readonly ?ProgramBlockKind $kind = null,
        public readonly ?int $progress = null,
        public readonly ?string $id = null,
        ?string $title = null,
    ) {
        if (null !== $app && !preg_match('/^[A-Za-z0-9_.+-]{1,32}$/D', $app)) {
            throw new InvalidArgumentException(\sprintf('The program status app "%s" must be 1 to 32 characters among letters, digits, "_", ".", "+" and "-".', $app));
        }

        if (null !== $id) {
            self::validateId($id);
        }

        if (null !== $kind && ProgramState::Blocked !== $state) {
            throw new InvalidArgumentException(\sprintf('A program status kind is only supported for the "blocked" state, "%s" given.', $state->value));
        }

        if (null !== $progress) {
            if (ProgramState::Working !== $state && ProgramState::Blocked !== $state) {
                throw new InvalidArgumentException(\sprintf('A program status progress is only supported for the "working" and "blocked" states, "%s" given.', $state->value));
            }

            if ($progress < 0 || $progress > 100) {
                throw new InvalidArgumentException(\sprintf('The program status progress must be between 0 and 100, %d given.', $progress));
            }
        }

        $this->message = self::normalizeText($message, self::MAX_MESSAGE_BYTES);
        $this->title = self::normalizeText($title, self::MAX_TITLE_BYTES);
    }

    /**
     * Returns the sequence removing the record of a task and the records beneath it, or every record when no id is given.
     */
    public static function toClearEscapeSequence(?string $id = null): string
    {
        if (null === $id) {
            return "\x1b]7501;state=clear\x1b\\";
        }

        self::validateId($id);

        return "\x1b]7501;state=clear:id={$id}\x1b\\";
    }

    public function toEscapeSequence(): string
    {
        $pairs = ['state='.$this->state->value];
        if (null !== $this->id) {
            $pairs[] = 'id='.$this->id;
        }
        if (null !== $this->kind) {
            $pairs[] = 'kind='.$this->kind->value;
        }
        if (null !== $this->progress) {
            $pairs[] = 'progress='.$this->progress;
        }
        if (null !== $this->app) {
            $pairs[] = 'app='.$this->app;
        }
        if (null !== $this->title) {
            $pairs[] = 'title='.base64_encode($this->title);
        }
        if (null !== $this->message) {
            $pairs[] = 'msg='.base64_encode($this->message);
        }

        return "\x1b]7501;".implode(':', $pairs)."\x1b\\";
    }

    private static function validateId(string $id): void
    {
        if (\strlen($id) > self::MAX_ID_BYTES || !preg_match(self::ID_PATTERN, $id)) {
            throw new InvalidArgumentException(\sprintf('The program status id "%s" must be up to 8 "/"-separated segments of 1 to 32 characters among letters, digits, "_", ".", "+" and "-", and at most 128 bytes.', $id));
        }
    }

    /**
     * Terminals discard a whole report whose text holds a control character
     * or exceeds the size limit, so the text is made safe instead.
     */
    private static function normalizeText(?string $text, int $maxBytes): ?string
    {
        if (null === $text) {
            return null;
        }

        $text = trim(preg_replace("/(?:[\x00-\x1f\x7f]|\xc2[\x80-\x9f])+/", ' ', mb_scrub(AnsiUtils::stripAnsiCodes($text), 'UTF-8')));
        if (\strlen($text) > $maxBytes) {
            $text = substr($text, 0, $maxBytes);
            while (!preg_match('//u', $text)) {
                $text = substr($text, 0, -1);
            }
            $text = rtrim($text);
        }

        return '' !== $text ? $text : null;
    }
}
