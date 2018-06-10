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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class LintCommand extends Command
{
    protected static $defaultName = 'lint:twig';

    private $twig;

    public function __construct(Environment $twig)
    {
        parent::__construct();

        $this->twig = $twig;
    }

    protected function configure()
    {
        $this
            ->setDescription('Lints a template and outputs encountered errors')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format', 'txt')
            ->addArgument('filename', InputArgument::IS_ARRAY)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command lints a template and outputs to STDOUT
the first encountered syntax error.

You can validate the syntax of contents passed from STDIN:

  <info>cat filename | php %command.full_name%</info>

Or the syntax of a file:

  <info>php %command.full_name% filename</info>

Or of a whole directory:

  <info>php %command.full_name% dirname</info>
  <info>php %command.full_name% dirname --format=json</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $filenames = $input->getArgument('filename');

        if (0 === count($filenames)) {
            if (0 !== ftell(STDIN)) {
                throw new RuntimeException('Please provide a filename or pipe template content to STDIN.');
            }

            $template = '';
            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }

            return $this->display($input, $output, $io, array($this->validate($template, uniqid('sf_', true))));
        }

        $filesInfo = $this->getFilesInfo($filenames);

        return $this->display($input, $output, $io, $filesInfo);
    }

    private function getFilesInfo(array $filenames)
    {
        $filesInfo = array();
        foreach ($filenames as $filename) {
            foreach ($this->findFiles($filename) as $file) {
                $filesInfo[] = $this->validate(file_get_contents($file), $file);
            }
        }

        return $filesInfo;
    }

    protected function findFiles($filename)
    {
        if (is_file($filename)) {
            return array($filename);
        } elseif (is_dir($filename)) {
            return Finder::create()->files()->in($filename)->name('*.twig');
        }

        throw new RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
    }

    private function validate($template, $file)
    {
        $realLoader = $this->twig->getLoader();
        try {
            $temporaryLoader = new ArrayLoader(array((string) $file => $template));
            $this->twig->setLoader($temporaryLoader);
            $nodeTree = $this->twig->parse($this->twig->tokenize(new Source($template, (string) $file)));
            $this->twig->compile($nodeTree);
            $this->twig->setLoader($realLoader);
        } catch (Error $e) {
            $this->twig->setLoader($realLoader);

            return array('template' => $template, 'file' => $file, 'line' => $e->getTemplateLine(), 'valid' => false, 'exception' => $e);
        }

        return array('template' => $template, 'file' => $file, 'valid' => true);
    }

    private function display(InputInterface $input, OutputInterface $output, SymfonyStyle $io, $files)
    {
        switch ($input->getOption('format')) {
            case 'txt':
                return $this->displayTxt($output, $io, $files);
            case 'json':
                return $this->displayJson($output, $files);
            default:
                throw new InvalidArgumentException(sprintf('The format "%s" is not supported.', $input->getOption('format')));
        }
    }

    private function displayTxt(OutputInterface $output, SymfonyStyle $io, $filesInfo)
    {
        $errors = 0;

        foreach ($filesInfo as $info) {
            if ($info['valid'] && $output->isVerbose()) {
                $io->comment('<info>OK</info>'.($info['file'] ? sprintf(' in %s', $info['file']) : ''));
            } elseif (!$info['valid']) {
                ++$errors;
                $this->renderException($io, $info['template'], $info['exception'], $info['file']);
            }
        }

        if (0 === $errors) {
            $io->success(sprintf('All %d Twig files contain valid syntax.', count($filesInfo)));
        } else {
            $io->warning(sprintf('%d Twig files have valid syntax and %d contain errors.', count($filesInfo) - $errors, $errors));
        }

        return min($errors, 1);
    }

    private function displayJson(OutputInterface $output, $filesInfo)
    {
        $errors = 0;

        array_walk($filesInfo, function (&$v) use (&$errors) {
            $v['file'] = (string) $v['file'];
            unset($v['template']);
            if (!$v['valid']) {
                $v['message'] = $v['exception']->getMessage();
                unset($v['exception']);
                ++$errors;
            }
        });

        $output->writeln(json_encode($filesInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return min($errors, 1);
    }

    private function renderException(OutputInterface $output, $template, Error $exception, $file = null)
    {
        $line = $exception->getTemplateLine();

        if ($file) {
            $output->text(sprintf('<error> ERROR </error> in %s (line %s)', $file, $line));
        } else {
            $output->text(sprintf('<error> ERROR </error> (line %s)', $line));
        }

        foreach ($this->getContext($template, $line) as $lineNumber => $code) {
            $output->text(sprintf(
                '%s %-6s %s',
                $lineNumber === $line ? '<error> >> </error>' : '    ',
                $lineNumber,
                $code
            ));
            if ($lineNumber === $line) {
                $output->text(sprintf('<error> >> %s</error> ', $exception->getRawMessage()));
            }
        }
    }

    private function getContext($template, $line, $context = 3)
    {
        $lines = explode("\n", $template);

        $position = max(0, $line - $context);
        $max = min(count($lines), $line - 1 + $context);

        $result = array();
        while ($position < $max) {
            $result[$position + 1] = $lines[$position];
            ++$position;
        }

        return $result;
    }
}
