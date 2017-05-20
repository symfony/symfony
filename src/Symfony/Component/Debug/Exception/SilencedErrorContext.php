<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

/**
 * Data Object that represents a Silenced Error.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SilencedErrorContext implements \JsonSerializable
{
    private $severity;
    private $file;
    private $line;

    public function __construct($severity, $file, $line)
    {
        $this->severity = $severity;
        $this->file = $file;
        $this->line = $line;
    }

    public function getSeverity()
    {
        return $this->severity;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function JsonSerialize()
    {
        return array(
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
        );
    }
}
