<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Helper
{
    /**
     * @param int $time
     *
     * @return string
     */
    public static function formatTime(int $time)
    {
        $hours = floor($time / 3600);
        $minutes = floor(($time / 60) % 60);
        $seconds = $time % 60;

        $hours = !empty($hours) ? $hours.'h' : '';
        $minutes = !empty($minutes) ? $minutes.'m' : '';
        $seconds = !empty($seconds) ? $seconds.'s' : '';

        $timeParts = [$hours, $minutes, $seconds];
        $timeParts = array_filter($timeParts, function ($timePart) {
            return !empty($timePart);
        });

        return implode(' ', $timeParts);
    }
}
