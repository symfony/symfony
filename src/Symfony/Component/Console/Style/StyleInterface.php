<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style;

/**
 * Output style helpers.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface StyleInterface
{
    /**
     * Formats a command title.
     *
     * @param string $message
     */
    public function title(string $message): void;

    /**
     * Formats a section title.
     *
     * @param string $message
     */
    public function section(string $message): void;

    /**
     * Formats a list.
     */
    public function listing(array $elements): void;

    /**
     * Formats informational text.
     *
     * @param string|array $message
     */
    public function text($message): void;

    /**
     * Formats a success result bar.
     *
     * @param string|array $message
     */
    public function success($message): void;

    /**
     * Formats an error result bar.
     *
     * @param string|array $message
     */
    public function error($message): void;

    /**
     * Formats an warning result bar.
     *
     * @param string|array $message
     */
    public function warning($message): void;

    /**
     * Formats a note admonition.
     *
     * @param string|array $message
     */
    public function note($message): void;

    /**
     * Formats a caution admonition.
     *
     * @param string|array $message
     */
    public function caution($message): void;

    /**
     * Formats a table.
     */
    public function table(array $headers, array $rows): void;

    /**
     * Asks a question.
     *
     * @param string        $question
     * @param string|null   $default
     * @param callable|null $validator
     *
     * @return string
     */
    public function ask(string $question, ?string $default = null, ?callable $validator = null): string;

    /**
     * Asks a question with the user input hidden.
     *
     * @param string        $question
     * @param callable|null $validator
     *
     * @return string
     */
    public function askHidden(string $question, ?callable $validator = null): string;

    /**
     * Asks for confirmation.
     *
     * @param string $question
     * @param bool   $default
     *
     * @return bool
     */
    public function confirm(string $question, bool $default = true): bool;

    /**
     * Asks a choice question.
     *
     * @param string          $question
     * @param array           $choices
     * @param string|int|null $default
     *
     * @return string
     */
    public function choice(string $question, array $choices, $default = null): string;

    /**
     * Add newline(s).
     *
     * @param int $count The number of newlines
     */
    public function newLine(int $count = 1): void;

    /**
     * Starts the progress output.
     *
     * @param int $max Maximum steps (0 if unknown)
     */
    public function progressStart(int $max = 0): void;

    /**
     * Advances the progress output X steps.
     *
     * @param int $step Number of steps to advance
     */
    public function progressAdvance(int $step = 1): void;

    /**
     * Finishes the progress output.
     */
    public function progressFinish(): void;
}
