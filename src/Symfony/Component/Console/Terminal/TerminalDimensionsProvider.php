<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Terminal;

class TerminalDimensionsProvider
{
    /**
     * @var int[]
     */
    private $terminalDimensions = array();

    /**
     * Tries to figure out the terminal dimensions based on the current environment.
     *
     * @return int[] Array containing width and height
     */
    public function getTerminalDimensions()
    {
        if ($this->terminalDimensions) {
            return $this->terminalDimensions;
        }

        if ($this->isWindowsEnvironment()) {
            // extract [w, H] from "wxh (WxH)"
            if (preg_match('/^(\d+)x\d+ \(\d+x(\d+)\)$/', trim(getenv('ANSICON')), $matches)) {
                return array((int) $matches[1], (int) $matches[2]);
            }
            // extract [w, h] from "wxh"
            if (preg_match('/^(\d+)x(\d+)$/', $this->getConsoleMode(), $matches)) {
                return array((int) $matches[1], (int) $matches[2]);
            }
        }

        if ($sttyString = $this->getSttyColumns()) {
            // extract [w, h] from "rows h; columns w;"
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                return array((int) $matches[2], (int) $matches[1]);
            }
            // extract [w, h] from "; h rows; w columns"
            if (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                return array((int) $matches[2], (int) $matches[1]);
            }
        }

        return array(null, null);
    }

    /**
     * Tries to figure out the terminal width in which this application runs.
     *
     * @return int|null
     */
    public function getTerminalWidth()
    {
        $terminalDimensions = $this->getTerminalDimensions();

        return $terminalDimensions[0];
    }

    /**
     * Tries to figure out the terminal height in which this application runs.
     *
     * @return int|null
     */
    public function getTerminalHeight()
    {
        $terminalDimensions = $this->getTerminalDimensions();

        return $terminalDimensions[1];
    }

    /**
     * Sets terminal dimensions.
     *
     * Can be useful to force terminal dimensions for functional tests.
     *
     * @param int $width
     * @param int $height
     */
    public function setTerminalDimensions($width, $height)
    {
        $this->terminalDimensions = array($width, $height);
    }

    /**
     * Runs and parses mode CON if it's available, suppressing any error output.
     *
     * @return string <width>x<height> or null if it could not be parsed
     */
    private function getConsoleMode()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = proc_open('mode CON', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if (preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
                return $matches[2].'x'.$matches[1];
            }
        }
    }

    /**
     * Runs and parses stty -a if it's available, suppressing any error output.
     *
     * @return string
     */
    private function getSttyColumns()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $info;
        }
    }

    /**
     * @return bool
     */
    private function isWindowsEnvironment()
    {
        return '\\' === DIRECTORY_SEPARATOR;
    }
}
