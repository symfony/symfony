<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TemporaryStorage
{
    private $directory;

    private $secret;

    private $nestingLevels;

    public function __construct($secret, $nestingLevels = 3, $directory = null)
    {
        if (empty($directory)) {
            $directory = sys_get_temp_dir();
        }

        $this->directory = realpath($directory);
        $this->secret = $secret;
        $this->nestingLevels = $nestingLevels;
    }

    protected function generateHashInfo($token)
    {
        return $this->secret . $token;
    }

    protected function generateHash($token)
    {
        return md5($this->generateHashInfo($token));
    }

    public function getTempDir($token)
    {
        if (!is_string($token)) {
            throw new UnexpectedTypeException($token, 'string');
        }

        $hash = $this->generateHash($token);

        if (strlen($hash) < $this->nestingLevels) {
            throw new FileException(sprintf(
                    'For %s nesting levels the hash must have at least %s characters', $this->nestingLevels, $this->nestingLevels));
        }

        $directory = $this->directory;

        for ($i = 0; $i < ($this->nestingLevels - 1); ++$i) {
            $directory .= DIRECTORY_SEPARATOR . $hash{$i};
        }

        return $directory . DIRECTORY_SEPARATOR . substr($hash, $i);
    }
}