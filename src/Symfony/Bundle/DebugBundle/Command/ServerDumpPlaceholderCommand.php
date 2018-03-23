<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\Command\ServerDumpCommand;
use Symfony\Component\VarDumper\Server\DumpServer;

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
        (new SymfonyStyle($input, $output))->getErrorStyle()->warning('In order to use the VarDumper server, set the "debug.dump_destination" config option to "tcp://%env(VAR_DUMPER_SERVER)%"');

        return 8;
    }
}
