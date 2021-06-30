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
     */
    public function title(string $message);

    /**
     * Formats a section title.
     */
    public function section(string $message);

    /**
     * Formats a list.
     */
    public function listing(array $elements);

    /**
     * Formats informational text.
     */
    public function text(string|array $message);

    /**
     * Formats a success result bar.
     */
    public function success(string|array $message);

    /**
     * Formats an error result bar.
     */
    public function error(string|array $message);

    /**
     * Formats an warning result bar.
     */
    public function warning(string|array $message);

    /**
     * Formats a note admonition.
     */
    public function note(string|array $message);

    /**
     * Formats a caution admonition.
     */
    public function caution(string|array $message);

    /**
     * Formats a table.
     */
    public function table(array $headers, array $rows);

    /**
     * Asks a question.
     *
     * @return mixed
     */
    public function ask(string $question, string $default = null, callable $validator = null);

    /**
     * Asks a question with the user input hidden.
     *
     * @return mixed
     */
    public function askHidden(string $question, callable $validator = null);

    /**
     * Asks for confirmation.
     *
     * @return bool
     */
    public function confirm(string $question, bool $default = true);

    /**
     * Asks a choice question.
     *
     * @return mixed
     */
    public function choice(string $question, array $choices, mixed $default = null);

    /**
     * Add newline(s).
     */
    public function newLine(int $count = 1);

    /**
     * Starts the progress output.
     */
    public function progressStart(int $max = 0);

    /**
     * Advances the progress output X steps.
     */
    public function progressAdvance(int $step = 1);

    /**
     * Finishes the progress output.
     */
    public function progressFinish();
}
