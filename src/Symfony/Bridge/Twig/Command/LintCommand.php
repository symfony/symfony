<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class LintCommand extends Command
{
    private $twig;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = 'twig:lint')
    {
        parent::__construct($name);
    }

    /**
     * Sets the twig environment
     *
     * @param \Twig_Environment $twig
     */
    public function setTwigEnvironment(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @return \Twig_Environment $twig
     */
    protected function getTwigEnvironment()
    {
        return $this->twig;
    }

    protected function configure()
    {
        $this
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

<info>cat filename | php %command.full_name%</info>

The command gets the template contents from stdin and validates its syntax.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $twig = $this->getTwigEnvironment();
        $template = null;
        $filename = $input->getArgument('filename');

        if (!$filename) {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException("Please provide a filename or pipe template content to stdin.");
            }

            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }

            return $this->validateTemplate($twig, $output, $template);
        }

        $files = $this->findFiles($filename);

        $errors = 0;
        foreach ($files as $file) {
            $errors += $this->validateTemplate($twig, $output, file_get_contents($file), $file);
        }

        return $errors > 0 ? 1 : 0;
    }

    protected function findFiles($filename)
    {
        if (is_file($filename)) {
            return array($filename);
        } elseif (is_dir($filename)) {
            return Finder::create()->files()->in($filename)->name('*.twig');
        }

        throw new \RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
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
        $line =  $exception->getTemplateLine();
        $lines = $this->getContext($template, $line);

        if ($file) {
            $output->writeln(sprintf("<error>KO</error> in %s (line %s)", $file, $line));
        } else {
            $output->writeln(sprintf("<error>KO</error> (line %s)", $line));
        }

        foreach ($lines as $no => $code) {
            $output->writeln(sprintf(
                "%s %-6s %s",
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
