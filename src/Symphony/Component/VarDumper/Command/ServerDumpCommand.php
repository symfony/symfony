<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Command;

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Exception\InvalidArgumentException;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\VarDumper\Cloner\Data;
use Symphony\Component\VarDumper\Command\Descriptor\CliDescriptor;
use Symphony\Component\VarDumper\Command\Descriptor\DumpDescriptorInterface;
use Symphony\Component\VarDumper\Command\Descriptor\HtmlDescriptor;
use Symphony\Component\VarDumper\Dumper\CliDumper;
use Symphony\Component\VarDumper\Dumper\HtmlDumper;
use Symphony\Component\VarDumper\Server\DumpServer;

/**
 * Starts a dump server to collect and output dumps on a single place with multiple formats support.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class ServerDumpCommand extends Command
{
    protected static $defaultName = 'server:dump';

    private $server;

    /** @var DumpDescriptorInterface[] */
    private $descriptors;

    public function __construct(DumpServer $server, array $descriptors = array())
    {
        $this->server = $server;
        $this->descriptors = $descriptors + array(
            'cli' => new CliDescriptor(new CliDumper()),
            'html' => new HtmlDescriptor(new HtmlDumper()),
        );

        parent::__construct();
    }

    protected function configure()
    {
        $availableFormats = implode(', ', array_keys($this->descriptors));

        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, sprintf('The output format (%s)', $availableFormats), 'cli')
            ->setDescription('Starts a dump server that collects and displays dumps in a single place')
            ->setHelp(<<<'EOF'
<info>%command.name%</info> starts a dump server that collects and displays
dumps in a single place for debugging you application:

  <info>php %command.full_name%</info>

You can consult dumped data in HTML format in your browser by providing the <comment>--format=html</comment> option
and redirecting the output to a file:

  <info>php %command.full_name% --format="html" > dump.html</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymphonyStyle($input, $output);
        $format = $input->getOption('format');

        if (!$descriptor = $this->descriptors[$format] ?? null) {
            throw new InvalidArgumentException(sprintf('Unsupported format "%s".', $format));
        }

        $errorIo = $io->getErrorStyle();
        $errorIo->title('Symphony Var Dumper Server');

        $this->server->start();

        $errorIo->success(sprintf('Server listening on %s', $this->server->getHost()));
        $errorIo->comment('Quit the server with CONTROL-C.');

        $this->server->listen(function (Data $data, array $context, int $clientId) use ($descriptor, $io) {
            $descriptor->describe($io, $data, $context, $clientId);
        });
    }
}
