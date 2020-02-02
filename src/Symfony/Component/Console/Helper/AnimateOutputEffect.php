<?php

namespace Symfony\Component\Console\Helper;


use Symfony\Component\Console\Output\ConsoleAnimateOutput;

/**
 * Provides some custom effect to register in ConsoleAnimateOutput
 *
 * @author Jibé Barth <barth.jib@gmail.com>
 */
class AnimateOutputEffect
{
    public static function progressive(ConsoleAnimateOutput $output): \Closure
    {
        return static function ($message, $newLine) use ($output) {
            foreach (preg_split('/(\\033\[[\d;]+\d+m)|(.)/u', $message, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE) as $char) {
                $output->directWrite($char, false);

                if (mb_strlen($char) === 1) {
                    usleep($output->getUsleepDuration());
                }
            }

            if ($newLine === true) {
                $output->directWrite(PHP_EOL, false);
            }
        };
    }

    public static function splitFlap(ConsoleAnimateOutput $output): \Closure
    {
        return static function(string $message, bool $newLine) use ($output) {
            $totalStr = '';
            $output->directWrite("\033[s", false);
            foreach (preg_split('/(\\033\[[\d;]+\d+m)|(.)/u', $message, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE) as $char) {
                $current = ord('!');
                $limit = 50;
                $shouldSplitFlapChar = ctype_alnum($char) && mb_strlen($char) === 1;

                while ($current < $limit && $shouldSplitFlapChar) {
                    $output->directWrite("\033[u", false);
                    $output->directWrite("\033[0J", false);
                    $output->directWrite($totalStr . mb_convert_encoding('&#' . $current . ';', 'UTF-8', 'HTML-ENTITIES'), false);
                    usleep($output->getUsleepDuration());
                    $current++;
                }

                $totalStr .= $char;
                $output->directWrite("\033[u", false);
                $output->directWrite("\033[0J", false);
                $output->directWrite($totalStr, false);
                if ($shouldSplitFlapChar) {
                    usleep($output->getUsleepDuration());
                }
            }
            if ($newLine === true) {
                $output->directWrite(PHP_EOL, false);
            }
        };
    }

    public static function glitch(ConsoleAnimateOutput $output, int $duration): \Closure
    {
        return static function(string $message, bool $newLine) use ($output, $duration) {
            $glitchChars = array_merge(range('!', 'z'));
            // Save cursor position
            $output->directWrite("\033[s", false);

            $currentSlowDown = $output->getSlowDown();
            $output->setSlowDown(ConsoleAnimateOutput::WRITE_VERY_SLOW);

            $duration = (int) microtime(true) + $duration;
            while (microtime(true) <= $duration) {
                $newMessage = '';

                foreach (preg_split('/(\\033\[[\d;]+\d+m)|(.)/u', $message, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE) as $char) {
                    if ($char !== ' ' && mb_strlen($char) === 1 && random_int(0, 100) >= 90) {
                        $newMessage .= $glitchChars[random_int(0, count($glitchChars) - 1)];
                        continue;
                    }
                    $newMessage .= $char;
                }

                // Restore cursor position
                $output->directWrite("\033[u", false);
                // Restore erase text after cursor
                $output->directWrite("\033[0J", false);
                $output->directWrite($newMessage, false);

                usleep($output->getUsleepDuration() * 2);
            }

            $output->directWrite("\033[u", false);
            $output->directWrite("\033[0J", false);
            $output->directWrite($message, $newLine);

            $output->setSlowDown($currentSlowDown);
        };
    }
}
