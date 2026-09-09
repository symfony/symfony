<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder;

enum RecorderMode
{
    /**
     * Records all HTTP requests into the HAR file.
     */
    case Record;

    /**
     * Replays HTTP requests from the HAR file.
     */
    case Replay;

    /**
     * Completely ignores the recording system and executes requests normally.
     */
    case Passthrough;
}
