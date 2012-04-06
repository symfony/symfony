<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 */
class LintCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('twig:lint')
            ->setDescription('Lints a template and outputs eventual errors.')
            ->addArgument('filename')
            ->setHelp(<<<EOF
the <info>%command.name%</info> command lints a template and outputs to stdout
the first encountered syntax error.

<info>php %command.full_name% filename</info>

The command will get the contents of "filename" and will validates its syntax.

<info>cat filename | php %command.full_name%</info>

The command will get the template contents from stdin and will validates its syntax.

This command will return these error codes:
  - 1 if template is invalid
  - 2 if file doesn't exists or stdin is empty.
EOF
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $twig = $this->getContainer()->get('twig');
        $template = null;
        $filename = $input->getArgument('filename');

        if ($filename && !is_readable($filename)) {
            $output->writeln(sprintf('<error>File %s is not readable</error>', $filename));

            return 2;
        }

        if ($filename) {
            $template = file_get_contents($filename);
        } else {
            if (0 !== ftell(STDIN)) {
                $output->writeln(sprintf('<error>Please provide a filename or pipe template content to stdin.</error>'));

                return 2;
            }
            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }
        }

        try {
            $twig->parse($twig->tokenize($template));
        } catch(\Twig_Error_Syntax $e) {
            $output->writeln($e->getMessage());

            return 1;
        }

        $output->writeln("<info>Template's syntax is valid.</info>");
    }
}

