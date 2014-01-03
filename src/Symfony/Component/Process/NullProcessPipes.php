<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\RuntimeException;

/**
 * NullProcessPipes allow redirecting output to dev/null without a subshell. Useful for processes that communicate
 * over other means.
 */
class NullProcessPipes extends ProcessPipes
{
    /**
     * Returns an array of descriptors for the use of proc_open.
     *
     * @return array
     */
    public function getDescriptors()
    {
        $nullfile = defined('PHP_WINDOWS_VERSION_BUILD') ? 'NUL' : '/dev/null';
        return array(

            array('pipe', 'r'), // stdin
            array('file', $nullfile, 'a+'), // stdout
            array('file', $nullfile, 'a+'), //stderr
        );
    }
}

