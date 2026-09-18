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

enum RecorderMode: string
{
    /**
     * Replays HTTP requests from the HAR file, and throws when one is missing without reaching the network.
     */
    case Replay = 'replay';

    /**
     * Makes the real HTTP requests and records them into the HAR file.
     */
    case Record = 'record';

    /**
     * Replays HTTP requests from the HAR file, and makes and records the ones it does not contain yet.
     */
    case Missing = 'missing';

    /**
     * Completely ignores the recording system and executes requests normally.
     */
    case Passthrough = 'passthrough';
}
