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
            ->setDescription('Lints a template and outputs encountered errors')
            ->addArgument('filename')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints a template and outputs to stdout
the first encountered syntax error.

  <info>php %command.full_name% filename</info>

The command gets the contents of <comment>filename</comment> and validates its syntax.

  <info>php %command.full_name% dirname</info>

The command finds all twig templates in <comment>dirname</comment> and validates the syntax
of each Twig template.

  <info>php %command.full_name% @AcmeMyBundle</info>

The command finds all twig templates in the <comment>AcmeMyBundle</comment> bundle and validates
the syntax of each Twig template.

  <info>cat filename | php %command.full_name%</info>

The command gets the template contents from stdin and validates its syntax.
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
                throw new \RuntimeException('Please provide a filename or pipe template content to stdin.');
            }

            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }

            return $this->validateTemplate($twig, $output, $template);
        }

        if (0 !== strpos($filename, '@') && !is_readable($filename)) {
            throw new \RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
        }

        if (is_file($filename)) {
            $files = array($filename);
        } elseif (is_dir($filename)) {
            $files = Finder::create()->files()->in($filename)->name('*.twig');
        } else {
            $dir = $this->getApplication()->getKernel()->locateResource($filename);
            $files = Finder::create()->files()->in($dir)->name('*.twig');
        }

        $errors = 0;
        foreach ($files as $file) {
            $errors += $this->validateTemplate($twig, $output, file_get_contents($file), $file);
        }

        return $errors > 0 ? 1 : 0;
    }

    protected function validateTemplate(\Twig_Environment $twig, OutputInterface $output, $template, $file = null)
    {
        try {
            $twig->parse($twig->tokenize($template, $file ? (string) $file : null));
            $output->writeln('<info>OK</info>'.($file ? sprintf(' in %s', $file) : ''));
        } catch (\Twig_Error $e) {
            $this->renderException($output, $template, $e, $file);

            return 1;
        }

        return 0;
    }

    protected function renderException(OutputInterface $output, $template, \Twig_Error $exception, $file = null)
    {
        $line = $exception->getTemplateLine();
        $lines = $this->getContext($template, $line);

        if ($file) {
            $output->writeln(sprintf('<error>KO</error> in %s (line %s)', $file, $line));
        } else {
            $output->writeln(sprintf('<error>KO</error> (line %s)', $line));
        }

        foreach ($lines as $no => $code) {
            $output->writeln(sprintf(
                '%s %-6s %s',
                $no == $line ? '<error>>></error>' : '  ',
                $no,
                $code
            ));
            if ($no == $line) {
                $output->writeln(sprintf('<error>>> %s</error> ', $exception->getRawMessage()));
            }
        }
    }

    protected function getContext($template, $line, $context = 3)
    {
        $lines = explode("\n", $template);

        $position = max(0, $line - $context);
        $max = min(count($lines), $line - 1 + $context);

        $result = array();
        while ($position < $max) {
            $result[$position + 1] = $lines[$position];
            $position++;
        }

        return $result;
    }
}
