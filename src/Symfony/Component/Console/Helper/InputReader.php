<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Janusz Jablonski <januszjablonski.pl@gmail.com>
 */
class InputReader
{
    const CTRL_SPACE = "\000";
    const END_OF_TRANSMISSION = "\004";
    const TAB = "\011";
    const FORM_FEED = "\12"; // alias: \n
    const CARRIAGE_RETURN = "\13";
    const CTRL_R = "\022";
    const ESCAPE = "\033";
    const BACKSPACE = "\177";
    const SAVE_CURSOR_POSITION = "\033\067";
    const RESTORE_CURSOR_POSITION = "\033\070";
    const INSERT = "\033\133\062\176";
    const DELETE = "\033\133\063\176";
    const HOME = "\033\117\110";
    const END = "\033\117\106";
    const PAGE_UP = "\033\133\065\176";
    const PAGE_DOWN = "\033\133\066\176";
    const ARROW_UP = "\033\133\101";
    const ARROW_DOWN = "\033\133\102";
    const ARROW_RIGHT = "\033\133\103";
    const ARROW_LEFT = "\033\133\104";
    const ERASE_TO_END_OF_LINE = "\033\133\113";
    const CURSOR_LEFT = "\033\133\061\104";

    protected $input = "";
    protected $print = "";
    protected $position = 0;

    private static $stty;
    private $sttyLastMode;
    private $inputStream;

    public function __construct($stream)
    {
        $this->inputStream = $stream;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return string
     */
    public function read(OutputInterface $output)
    {
        $this->activeEachKeyPressMode();
        $inputStream = $this->inputStream;
        $this->input = '';
        $this->print = '';
        $this->position = 0;
        $continue = true;
        $lastPosition = 0;
        while (!feof($inputStream) && true === $continue) {
            $inputChunk = fread($inputStream, 1024);

            try {
                while ('' !== $inputChunk) {
                    $used = $this->parseStream($inputChunk);
                    $inputChunk = mb_substr($inputChunk, $used);
                }
            } catch (\RuntimeException $e) {
                $continue = false;
            }

            $this->writeStream($output, $lastPosition);
            $lastPosition = $this->position;
        }
        $this->restoreMode();

        return $this->input;
    }

    /**
     * @return bool
     */
    public static function isEachKeyPressModeAvailable()
    {
        if (null === self::$stty) {
            exec('stty 2>&1', $output, $exitCode);
            self::$stty = $exitCode === 0;
        }

        return self::$stty;
    }

    /**
     * @param string $inputChunk
     *
     * @throws \RuntimeException
     * @return int
     */
    protected function parseStream($inputChunk)
    {
        $used = 1;
        if ($inputChunk[0] === self::BACKSPACE) {
            if (0 < $this->position) {
                $suffix = mb_substr($this->input, $this->position);
                $this->input = mb_substr($this->input, 0, $this->position - 1) . $suffix;
                $this->position--;
            }
        } elseif (decoct(ord($inputChunk[0])) <= 33) {
            $length = mb_strlen($this->input);
            if (0 == strncmp($inputChunk, self::ARROW_LEFT, 3)) {
                if (0 < $this->position) {
                    $this->position--;
                }
                $used = 3;
            } elseif (0 == strncmp($inputChunk, self::ARROW_RIGHT, 3)) {
                if ($this->position < mb_strlen($this->input)) {
                    $this->position++;
                }
                $used = 3;
            } elseif (
                0 == strncmp($inputChunk, self::ARROW_UP, 3)
                || 0 == strncmp($inputChunk, self::ARROW_DOWN, 3)
            ) {
                $used = 3;
            } elseif (0 == strncmp($inputChunk, self::DELETE, 4)) {
                if ($this->position < $length) {
                    $suffix = mb_substr($this->input, $this->position + 1);
                    $this->input = mb_substr($this->input, 0, $this->position) . $suffix;
                }
                $used = 4;
            } elseif ($inputChunk[0] === self::FORM_FEED) {
                $this->position = $length + 1;
                $this->print = $this->input . self::FORM_FEED;
                throw new \RuntimeException();
            }
        } else {
            $char = $inputChunk[0];
            $suffix = mb_substr($this->input, $this->position);
            $this->input = mb_substr($this->input, 0, $this->position) . $char . $suffix;
            $this->position += 1;
        }
        $this->print = $this->input;

        return $used;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @param int $lastPosition
     */
    private function writeStream(OutputInterface $output, $lastPosition)
    {
        $output->write(str_repeat(self::ARROW_LEFT, $lastPosition));
        $output->write(self::ERASE_TO_END_OF_LINE);
        if ($this->position < mb_strlen($this->print)) {
            $output->write(mb_substr($this->print, 0, $this->position));
            $output->write(self::SAVE_CURSOR_POSITION);
            $output->write(mb_substr($this->print, $this->position));
            $output->write(self::RESTORE_CURSOR_POSITION);
        } else {
            $output->write($this->print);
        }
    }

    private function activeEachKeyPressMode()
    {
        if (null == $this->sttyLastMode && self::isEachKeyPressModeAvailable()) {
            $this->sttyLastMode = shell_exec('stty -g');
            shell_exec('stty -icanon -echo');
        }
    }

    private function restoreMode()
    {
        if (null != $this->sttyLastMode && self::isEachKeyPressModeAvailable()) {
            shell_exec(sprintf('stty %s', $this->sttyLastMode));
            $this->sttyLastMode = null;
        }
    }

}
