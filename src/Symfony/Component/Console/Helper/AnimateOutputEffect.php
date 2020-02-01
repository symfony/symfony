<?php

namespace Symfony\Component\Console\Helper;


use Symfony\Component\Console\Output\ConsoleAnimateOutput;

/**
 * Provides some custom effect to register in ConsoleAnimateOutput
 * @author Jibé Barth <barth.jib@gmail.com>
 */
class AnimateOutputEffect
{
    public static function progressive(ConsoleAnimateOutput $output): \Closure
    {
        return function ($message, $newLine) use ($output) {
            foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $output->parentWrite($char, false);
                usleep($output->getUsleepDuration());
            }

            if ($newLine === true) {
                $output->parentWrite(PHP_EOL, false);
            }
        };
    }

    public static function splitFlap(ConsoleAnimateOutput $output): \Closure
    {
        return static function(string $message, bool $newLine) use ($output) {
            $totalStr = '';
            $output->parentWrite("\033[s", false);

            foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $current = ord('!');
                $limit = 50;
                do {
                    $output->parentWrite("\033[u", false);
                    $output->parentWrite("\033[0J", false);
                    $output->parentWrite($totalStr . mb_convert_encoding('&#' . $current . ';', 'UTF-8', 'HTML-ENTITIES'), false);
                    usleep($output->getUsleepDuration());
                    $current++;
                } while ($current < $limit && in_array($char, range('A','z')));

                $totalStr .= $char;
                $output->parentWrite("\033[u", false);
                $output->parentWrite("\033[0J", false);
                $output->parentWrite($totalStr, false);
                usleep($output->getUsleepDuration());
            }

            if ($newLine === true) {
                $output->parentWrite(PHP_EOL, false);
            }
        };
    }

    public static function glitch(ConsoleAnimateOutput $output, int $duration): \Closure
    {
        return function(string $message, bool $newLine) use ($output, $duration) {
            $glitchChars = array_merge(range('!', 'z'), range('€','¿'));
            // Save cursor position
            $output->parentWrite("\033[s", false);

            $currentSlowDown = $output->getSlowDown();

            $output->setSlowDown(ConsoleAnimateOutput::PROGRESSIVE_SLOW);
            $output->hideCursor();

            $duration = (int) microtime(true) + $duration;
            while (microtime(true) <= $duration) {
                $newMessage = '';

                foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                    if (random_int(0, 100) >= 90 && $char !== ' ') {
                        $newMessage .= $glitchChars[random_int(0, count($glitchChars) - 1)];
                    } else {
                        $newMessage .= $char;
                    }
                }

                // Restore cursor position
                $output->parentWrite("\033[u", false);
                // Restore erase text after cursor
                $output->parentWrite("\033[0J", false);
                $output->parentWrite($newMessage, false);
                usleep($output->getUsleepDuration()*2);
            }

            $output->showCursor();
            $output->parentWrite("\033[u", false);
            $output->parentWrite("\033[0J", false);
            $output->parentWrite($message, $newLine);

            $output->setSlowDown($currentSlowDown);
        };
    }
}
