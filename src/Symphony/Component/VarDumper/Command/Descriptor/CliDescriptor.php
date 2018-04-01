<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Command\Descriptor;

use Symphony\Component\Console\Input\ArrayInput;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;
use Symphony\Component\VarDumper\Cloner\Data;
use Symphony\Component\VarDumper\Dumper\CliDumper;

/**
 * Describe collected data clones for cli output.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class CliDescriptor implements DumpDescriptorInterface
{
    private $dumper;
    private $lastIdentifier;

    public function __construct(CliDumper $dumper)
    {
        $this->dumper = $dumper;
    }

    public function describe(OutputInterface $output, Data $data, array $context, int $clientId): void
    {
        $io = $output instanceof SymphonyStyle ? $output : new SymphonyStyle(new ArrayInput(array()), $output);

        $rows = array(array('date', date('r', $context['timestamp'])));
        $lastIdentifier = $this->lastIdentifier;
        $this->lastIdentifier = $clientId;

        $section = "Received from client #$clientId";
        if (isset($context['request'])) {
            $request = $context['request'];
            $this->lastIdentifier = $request['identifier'];
            $section = sprintf('%s %s', $request['method'], $request['uri']);
            if ($controller = $request['controller']) {
                $rows[] = array('controller', $controller);
            }
        } elseif (isset($context['cli'])) {
            $this->lastIdentifier = $context['cli']['identifier'];
            $section = '$ '.$context['cli']['command_line'];
        }

        if ($this->lastIdentifier !== $lastIdentifier) {
            $io->section($section);
        }

        if (isset($context['source'])) {
            $source = $context['source'];
            $rows[] = array('source', sprintf('%s on line %d', $source['name'], $source['line']));
            $file = $source['file_relative'] ?? $source['file'];
            $rows[] = array('file', $file);
            $fileLink = $source['file_link'] ?? null;
        }

        $io->table(array(), $rows);

        if (isset($fileLink)) {
            $io->writeln(array('<info>Open source in your IDE/browser:</info>', $fileLink));
            $io->newLine();
        }

        $this->dumper->dump($data);
        $io->newLine();
    }
}
