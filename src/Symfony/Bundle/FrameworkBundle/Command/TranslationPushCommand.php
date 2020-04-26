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
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * @final
 */
class TranslationPushCommand extends Command
{
    protected static $defaultName = 'translation:push';

    private $remotes;
    private $reader;
    private $defaultTransPath;
    private $transPaths;
    private $enabledLocales;

    public function __construct(Remotes $remotes, TranslationReaderInterface $reader, string $defaultTransPath = null, array $transPaths = [], array $enabledLocales = [])
    {
        $this->remotes = $remotes;
        $this->reader = $reader;
        $this->defaultTransPath = $defaultTransPath;
        $this->transPaths = $transPaths;
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
                new InputOption('locales', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the locels to pull', $this->enabledLocales),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'xlf'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version', '1.2'),
            ])
            ->setDescription('Push translations to a given remote.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> push translations to the given remote. Only new
translations are pushed, existing ones are not overwriten.

You can overwrite existing translations:

  <info>php %command.full_name% --force remote</info>

You can delete remote translations which are not present locally:

  <info>php %command.full_name% --delete-absolete remote</info>

Full example:

  <info>php %command.full_name% remote --force --delete-obslete --domains=messages,validators --locales=en</info>

This command will push all translations linked to domains messages & validators
for the locale en. Remote translations for the specified domains & locale will
be erased if they're not present locally and overwriten if it's the
case. Remote translations for others domains & locales will be ignored.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->enabledLocales)) {
            throw new InvalidArgumentException('You must defined framework.translator.enabled_locales config key in order to work with remotes.');
        }

        $locales = $input->getOption('locales');
        $domains = $input->getOption('domains');

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        $transPaths = $this->transPaths;
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }

        // Override with provided Bundle info
        foreach ($kernel->getBundles() as $bundle) {
            $bundleDir = $bundle->getPath();
            $transPaths[] = is_dir($bundleDir.'/Resources/translations') ? $bundleDir.'/Resources/translations' : $bundle->getPath().'/translations';
        }

        $translatorBag = new TranslatorBag();
        foreach ($locales as $locale) {
            $translatorBag->addCatalogue($this->loadCurrentMessages($locale, $transPaths));
        }

        $remoteTranslations = $this->remotes
            ->get($input->getArgument('remote'))
            ->read($domains ?? $translatorBag->getDomains(), $locales);


        // diff between $remoteTranslations and $localTranslations,
        // then write to remote the diff (aka. new translation not yet in the remote storage)
        return 0;
    }

    private function loadCurrentMessages(string $locale, array $transPaths): MessageCatalogue
    {
        $currentCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

        return $currentCatalogue;
    }
}
