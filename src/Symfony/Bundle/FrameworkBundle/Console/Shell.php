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
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class Shell extends BaseShell
{
    /**
     * Returns the shell header.
     *
     * @return string The header string
     *
     * @since v2.0.0
     */
    protected function getHeader()
    {
        return <<<EOF
<info>
      _____                  __                  ___
     / ____|                / _|                |__ \
    | (___  _   _ _ __ ___ | |_ ___  _ __  _   _   ) |
     \___ \| | | | '_ ` _ \|  _/ _ \| '_ \| | | | / /
     ____) | |_| | | | | | | || (_) | | | | |_| |/ /_
    |_____/ \__, |_| |_| |_|_| \___/|_| |_|\__, |____|
             __/ |                          __/ |
            |___/                          |___/

</info>
EOF
        .parent::getHeader();
    }
}
