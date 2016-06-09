<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

class Terminal
{
    private $width;
    private $height;

    /**
     * Gets the terminal width.
     *
     * @return int|null
     */
    public function getWidth()
    {
        if (null === $this->width) {
            $this->initDimensions();
        }

        return $this->width;
    }

    /**
     * Sets the terminal width.
     *
     * @param int
     */
    public function setWidth($width)
    {
        $this->width = $width;
    }

    /**
     * Gets the terminal height.
     *
     * @return int|null
     */
    public function getHeight()
    {
        if (null === $this->height) {
            $this->initDimensions();
        }

        return $this->height;
    }

    /**
     * Sets the terminal height.
     *
     * @param int
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    private function initDimensions()
    {
        if (null !== $this->width && null !== $this->height) {
            return;
        }

        $width = $height = null;
        if ($this->isWindowsEnvironment()) {
            if (preg_match('/^(\d+)x\d+ \(\d+x(\d+)\)$/', trim(getenv('ANSICON')), $matches)) {
                // extract [w, H] from "wxh (WxH)"
                $width = (int) $matches[1];
                $height = (int) $matches[2];
            } elseif (null != $dimensions = $this->getConsoleMode()) {
                // extract [w, h] from "wxh"
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        } elseif ($sttyString = $this->getSttyColumns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                // extract [w, h] from "rows h; columns w;"
                $width = (int) $matches[1];
                $height = (int) $matches[2];
            } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                // extract [w, h] from "; h rows; w columns"
                $width = (int) $matches[2];
                $heighth = (int) $matches[1];
            }
        }

        if (null === $this->width) {
            $this->width = $width;
        }

        if (null === $this->height) {
            $this->height = $height;
        }
    }

    /**
     * Runs and parses mode CON if it's available, suppressing any error output.
     *
     * @return array|null An array composed of the width and the height or null if it could not be parsed
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
                return array((int) $matches[2], (int) $matches[1]);
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
