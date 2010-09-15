<?php

namespace Symfony\Bundle\FrameworkBundle\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Command\InitApplicationCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BootstrapApplication.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BootstrapApplication extends BaseApplication
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('Symfony', Kernel::VERSION);

        $this->addCommand(new InitApplicationCommand());
    }
}
