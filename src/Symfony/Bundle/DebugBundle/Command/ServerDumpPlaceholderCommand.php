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

use Symfony\Component\Console\Command\Command;
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
class ServerDumpPlaceholderCommand extends Command
{
    private $replacedCommand;

    public function __construct(DumpServer $server = null, array $descriptors = [])
    {
        $this->replacedCommand = new ServerDumpCommand((new \ReflectionClass(DumpServer::class))->newInstanceWithoutConstructor(), $descriptors);

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDefinition($this->replacedCommand->getDefinition());
        $this->setHelp($this->replacedCommand->getHelp());
        $this->setDescription($this->replacedCommand->getDescription());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->getErrorStyle()->warning('In order to use the VarDumper server, set the "debug.dump_destination" config option to "tcp://%env(VAR_DUMPER_SERVER)%"');

        return 8;
    }
}
