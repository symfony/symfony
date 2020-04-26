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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Remotes;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * A command that parses templates to extract translation messages and adds them
 * into the translation files.
 *
 * @final
 */
class TranslationPullCommand extends Command
{
    protected static $defaultName = 'translation:pull';

    private $remotes;
    private $writer;
    private $reader;
    private $defaultLocale;
    private $defaultTransPath;
    private $enabledLocales;

    public function __construct(Remotes $remotes, TranslationWriterInterface $writer, TranslationReaderInterface $reader, string $defaultLocale, string $defaultTransPath = null, array $enabledLocales = [])
    {
        $this->remotes = $remotes;
        $this->writer = $writer;
        $this->reader = $reader;
        $this->defaultLocale = $defaultLocale;
        $this->defaultTransPath = $defaultTransPath;
        $this->enabledLocales = $enabledLocales;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $keys = $this->remotes->keys();
        $defaultRemote = 1 === count($keys) ? $keys[0] : null;

        $this
            ->setDefinition([
                new InputArgument('remote', null !== $defaultRemote ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'The remote to pull translations from.', $defaultRemote),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Override existing translations with updated ones'),
                new InputOption('delete-obsolete', null, InputOption::VALUE_NONE, 'Delete translations available locally but not on remote'),
                new InputOption('domains', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the domains to pull'),
                new InputOption('locales', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the locels to pull'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'xlf'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version', '1.2'),
            ])
            ->setDescription('Pull translations from a given remote.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> pull translations from the given remote. Only
new translations are pulled, existing ones are not overwriten.

You can overwrite existing translations:

  <info>php %command.full_name% --force remote</info>

You can remote local translations which are not present on the remote:

  <info>php %command.full_name% --delete-absolete remote</info>

Full example:

  <info>php %command.full_name% remote --force --delete-obslete --domains=messages,validators --locales=en</info>

This command will pull all translations linked to domains messages & validators
for the locale en. Local translations for the specified domains & locale will
be erased if they're not present on the remote and overwriten if it's the
case. Local translations for others domains & locales will be ignored.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $remoteTranslations = $this->remotes->get($input->getArgument('remote'))->read();

        //$this->writer->write($operation->getResult(), $input->getOption('output-format'), [
            //'path' => $bundleTransPath,
            //'default_locale' => $this->defaultLocale,
            //'xliff_version' => $input->getOption('xliff-version')
        //]);

        dump($this->remotes);
        return 0;
    }
}
