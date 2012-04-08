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
use Symfony\Component\Finder\Finder;

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

<info>php %command.full_name% dirname</info>

The command will find all twig templates in dirname and will validate the syntax
of each Twig template.

<info>php %command.full_name% @AcmeMyBundle</info>

The command will find all twig templates in bundle AcmeMyBundle and will validate
the syntax of each one.

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

        if (!$filename) {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException("Please provide a filename or pipe template content to stdin.");
            }

            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }

            return $twig->parse($twig->tokenize($template));
        }

        if (0 !== strpos($filename, '@') && !is_readable($filename)) {
            throw new \RuntimeException("File or directory '%s' is not readable");
        }

        $files = array();
        if (is_file($filename)) {
            $files = array($filename);
        } elseif (is_dir($filename)) {
            $files = Finder::create()->files()->in($filename)->name('*.twig');
        } else {
            $dir = $this->getApplication()->getKernel()->locateResource($filename);
            $files = Finder::create()->files()->in($dir)->name('*.twig');
        }

        foreach ($files as $file) {
            try {
                $twig->parse($twig->tokenize(file_get_contents($file)));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>Syntax error in %s</error>', $file));

                throw $e;
            }
        }

        $output->writeln('<info>No syntax error detected.</info>');
    }
}

