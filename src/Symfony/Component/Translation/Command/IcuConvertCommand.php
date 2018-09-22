<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Util\IcuMessageConverter;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * Convert to Intl styled message format.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class IcuConvertCommand extends Command
{
    protected static $defaultName = 'translation:convert-to-icu-messages';

    private $writer;
    private $reader;

    public function __construct(TranslationWriterInterface $writer, TranslationReaderInterface $reader)
    {
        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Convert from Symfony 3 plural format to ICU message format.')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale')
            ->addArgument('path', null, 'A file or a directory')
            ->addOption('domain', null, InputOption::VALUE_OPTIONAL, 'The messages domain')
            ->addOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'xlf')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        // Define Root Paths
        $transPaths = $kernel->getProjectDir().DIRECTORY_SEPARATOR.'translations';
        if (null !== $path) {
            $transPaths = $path;
        }

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($locale);
        if (!is_dir($transPaths)) {
            throw new \LogicException('The "path" must be a directory.');
        }
        $this->reader->read($transPaths, $currentCatalogue);

        $allMessages = $currentCatalogue->all($domain);
        if (null !== $domain) {
            $allMessages = array($domain => $allMessages);
        }

        $updated = array();
        foreach ($allMessages as $messageDomain => $messages) {
            foreach ($messages as $key => $message) {
                $updated[$messageDomain][$key] = IcuMessageConverter::convert($message);
            }
        }

        $this->writer->write(new MessageCatalogue($locale, $updated), $input->getOption('output-format'), array('path' => $transPaths));
    }
}
