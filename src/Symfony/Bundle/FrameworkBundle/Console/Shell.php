<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console;

use Symfony\Component\Console\Shell as BaseShell;

/**
 * Shell.
 *
 * @deprecated since version 2.8, to be removed in 3.0.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Shell extends BaseShell
{
    /**
     * Returns the shell header.
     *
     * @return string The header string
     */
    protected function getHeader()
    {
        return <<<EOF
<info>
      _____                  __
     / ____|                / _|
    | (___  _   _ _ __ ___ | |_ ___  _ __  _   _
     \___ \| | | | '_ ` _ \|  _/ _ \| '_ \| | | |
     ____) | |_| | | | | | | || (_) | | | | |_| |
    |_____/ \__, |_| |_| |_|_| \___/|_| |_|\__, |
             __/ |                          __/ |
            |___/                          |___/

</info>
EOF
        .parent::getHeader();
    }
}
