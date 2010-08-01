<?php

namespace Symfony\Bundle\FrameworkBundle\Console;

use Symfony\Components\Console\Shell as BaseShell;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Shell.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
      _____                  __                    ___  
     / ____|                / _|                  |__ \ 
    | (___  _   _ _ __ ___ | |_ ___  _ __  _   _     ) |
     \___ \| | | | '_ ` _ \|  _/ _ \| '_ \| | | |   / / 
     ____) | |_| | | | | | | || (_) | | | | |_| |  / /_ 
    |_____/ \__, |_| |_| |_|_| \___/|_| |_|\__, | |____|
             __/ |                          __/ |       
            |___/                          |___/        

</info>
EOF
        .parent::getHeader();
    }
}
