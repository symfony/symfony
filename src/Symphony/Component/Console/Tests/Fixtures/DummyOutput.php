<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Tests\Fixtures;

use Symphony\Component\Console\Output\BufferedOutput;

/**
 * Dummy output.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DummyOutput extends BufferedOutput
{
    /**
     * @return array
     */
    public function getLogs()
    {
        $logs = array();
        foreach (explode(PHP_EOL, trim($this->fetch())) as $message) {
            preg_match('/^\[(.*)\] (.*)/', $message, $matches);
            $logs[] = sprintf('%s %s', $matches[1], $matches[2]);
        }

        return $logs;
    }
}
