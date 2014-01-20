<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', 128);
}

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Validates YAML files syntax and output encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class YamlLintCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('yaml:lint')
            ->setDescription('Lints a file and outputs encountered errors')
            ->addArgument('filename', null, 'A file or a directory or STDIN')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format', 'txt')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints a YAML file and outputs to STDOUT
the first encountered syntax error.

You can validate the syntax of a file:

<info>php %command.full_name% filename</info>

Or of a whole directory:

<info>php %command.full_name% dirname</info>
<info>php %command.full_name% dirname --format=json</info>

Or all YAML files in a bundle:

<info>php %command.full_name% @AcmeDemoBundle</info>

You can also pass the YAML contents from STDIN:

<info>cat filename | php %command.full_name%</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');

        if (!$filename) {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException('Please provide a filename or pipe file content to STDIN.');
            }

            $content = '';
            while (!feof(STDIN)) {
                $content .= fread(STDIN, 1024);
            }

            return $this->display($input, $output, array($this->validate($content)));
        }

        if (0 !== strpos($filename, '@') && !is_readable($filename)) {
            throw new \RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
        }

        $files = array();
        if (is_file($filename)) {
            $files = array($filename);
        } elseif (is_dir($filename)) {
            $files = Finder::create()->files()->in($filename)->name('*.yml');
        } else {
            $dir = $this->getApplication()->getKernel()->locateResource($filename);
            $files = Finder::create()->files()->in($dir)->name('*.yml');
        }

        $filesInfo = array();
        foreach ($files as $file) {
            $filesInfo[] = $this->validate(file_get_contents($file), $file);
        }

        return $this->display($input, $output, $filesInfo);
    }

    private function validate($content, $file = null)
    {
        $this->parser = new Parser();
        try {
            $this->parser->parse($content);
        } catch (ParseException $e) {
            return array('file' => $file, 'valid' => false, 'message' => $e->getMessage());
        }

        return array('file' => $file, 'valid' => true);
    }

    private function display(InputInterface $input, OutputInterface $output, $files)
    {
        switch ($input->getOption('format')) {
            case 'txt':
                return $this->displayTxt($output, $files);
            case 'json':
                return $this->displayJson($output, $files);
            default:
                throw new \InvalidArgumentException(sprintf('The format "%s" is not supported.', $input->getOption('format')));
        }
    }

    private function displayTxt(OutputInterface $output, $filesInfo)
    {
        $errors = 0;

        foreach ($filesInfo as $info) {
            if ($info['valid'] && $output->isVerbose()) {
                $output->writeln('<info>OK</info>'.($info['file'] ? sprintf(' in %s', $info['file']) : ''));
            } elseif (!$info['valid']) {
                $errors++;
                $output->writeln(sprintf('<error>KO</error> in %s', $info['file']));
                $output->writeln(sprintf('<error>>> %s</error>', $info['message']));
            }
        }

        $output->writeln(sprintf('<comment>%d/%d valid files</comment>', count($filesInfo) - $errors, count($filesInfo)));

        return min($errors, 1);
    }

    private function displayJson(OutputInterface $output, $filesInfo)
    {
        $errors = 0;

        array_walk($filesInfo, function (&$v) use (&$errors) {
            $v['file'] = (string) $v['file'];
            if (!$v['valid']) {
                $errors++;
            }
        });

        $output->writeln(json_encode($filesInfo, JSON_PRETTY_PRINT));

        return min($errors, 1);
    }
}
