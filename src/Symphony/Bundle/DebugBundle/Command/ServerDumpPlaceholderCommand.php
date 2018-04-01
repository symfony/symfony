<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\DebugBundle\Command;

use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\VarDumper\Command\ServerDumpCommand;
use Symphony\Component\VarDumper\Server\DumpServer;

/**
 * A placeholder command easing VarDumper server discovery.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
class ServerDumpPlaceholderCommand extends ServerDumpCommand
{
    public function __construct(DumpServer $server = null, array $descriptors = array())
    {
        parent::__construct(new class() extends DumpServer {
            public function __construct()
            {
            }
        }, $descriptors);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new SymphonyStyle($input, $output))->getErrorStyle()->warning('In order to use the VarDumper server, set the "debug.dump_destination" config option to "tcp://%env(VAR_DUMPER_SERVER)%"');

        return 8;
    }
}
