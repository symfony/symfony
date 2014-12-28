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

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Validates XML files syntax and outputs encountered errors.
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 */
class XmlLintCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('xml:lint')
            ->setDescription('Lints a file and outputs encountered errors.')
            ->addArgument('filename', InputArgument::OPTIONAL, 'Path to a file or a directory')
            ->addOption('with-xsd', 'xsd', InputOption::VALUE_REQUIRED, 'Path to a xsd file.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints XML file(s).

By default, the command goes through the <comment>app</comment> and <comment>src</comment> directories of your project to validate XML files.

It offers the possibility to validate:
    - a specific file or files of a directory with the filename argument <info>php %command.full_name% filename</info>,
    - validate XML files against a xsd validation file with the option <info>--with-xsd</info>.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        $xsd      = $input->getOption('with-xsd');

        if (null !== $xsd && !is_file($xsd)) {
            throw new \RuntimeException(sprintf('"%s" is not a file.', $xsd));
        }

        /** If no file or directory supplied, validate all xml of the project **/
        if (null === $filename) {
            $appDir = $this->getContainer()->getParameter('kernel.root_dir');
            $appFiles = Finder::create()->files()->name('/\.xml$/')->in($appDir);
            $srcFiles = Finder::create()->files()->name('/\.xml$/')->in($appDir.'/../src');

            $files = array();
            foreach ($appFiles as $file) {
                $errorMessages[$file->getRealpath()] = $this->validateFile($file->getRealpath(), $output, $xsd);
            }

            foreach ($srcFiles as $file) {
                $errorMessages[$file->getRealpath()] = $this->validateFile($file->getRealpath(), $output, $xsd);
            }

            if (isset($errorMessages)) {
                $this->displayPrettyError($errorMessages, $output);
            }

            return;
        }

        /** If file or directory supplied **/
        $isFileOrDirectory = $this->IsFileOrDirectory($filename, $output);

        if ('file' === $isFileOrDirectory) {
            $errorMessages[$filename] = $this->validateFile($filename, $output, $xsd);
        } elseif ('directory' === $isFileOrDirectory) {
            $files = Finder::create()->files()->name('/\.xml$/')->in($filename);

            if (!count($files)) {
                $errorMessages[] = $output->writeln(sprintf('<comment>No files found in "%s" directory.</comment>', $directory));
            }

            foreach ($files as $file) {
                $errorMessages[$file->getRealpath()] = $this->validateFile($file->getRealpath(), $output, $xsd);
            }
        }

        if (isset($errorMessages)) {
            $this->displayPrettyError($errorMessages, $output);
        }
    }

    /**
     * Validates an XML file.
     *
     * @param string          $filename Absolute path to an XML file
     * @param OutputInterface $output   An OutputInterface instance
     * @param string          $xsd      Absolute path of a XSD file
     */
    private function validateFile($filename, OutputInterface $output, $xsd)
    {
        $output->writeln(sprintf('<comment>Checking "%s" file…</comment>', $filename));

        try {
            XmlUtils::loadFile($filename, $xsd);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Determines if the path supplied is a directory of a file.
     *
     * @param string          $filename Absolute path to an XML file
     * @param OutputInterface $output   An OutputInterface instance
     *
     * @return string
     *
     * @throws \RuntimeException When the path is neither a directory nor a file
     */
    private function IsFileOrDirectory($filename, OutputInterface $output)
    {
        if (null !== $filename && is_file($filename)) {
            return 'file';
        }

        if (null !== $filename && is_dir($filename)) {
            return 'directory';
        }

        throw new \RuntimeException(sprintf('File or directory "%s" is not readable.', $filename));
    }

    /**
     * Display the error for each files validated.
     *
     * @param array           $errorMessages Array of error messages got after validation
     * @param OutputInterface $output        An OutputInterface instance
     *
     * @throws \RuntimeException When the path is neither a directory nor a file
     */
    private function displayPrettyError(array $errorMessages, OutputInterface $output)
    {
        $message = null;

        foreach ($errorMessages as $filename => $errorMessage) {
            if (null !== $errorMessage) {
                $message .= $filename.": \n   - ".$errorMessage."\n";
            }
        }

        if (null !== $message) {
            throw new \RuntimeException(sprintf('%s', $message));
        }

        $output->writeln(sprintf("\n<info>File(s) are valid.</info>"));
    }
}
