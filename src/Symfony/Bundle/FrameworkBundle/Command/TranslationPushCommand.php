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
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\TranslationProviders;

final class TranslationPushCommand extends Command
{
    use TranslationTrait;

    protected static $defaultName = 'translation:push';

    private $providers;
    private $reader;
    private $transPaths;
    private $enabledLocales;

    public function __construct(TranslationProviders $providers, TranslationReaderInterface $reader, string $defaultTransPath = null, array $transPaths = [], array $enabledLocales = [])
    {
        $this->providers = $providers;
        $this->reader = $reader;
        $this->transPaths = $transPaths;
        $this->enabledLocales = $enabledLocales;

        if (null !== $defaultTransPath) {
            $this->transPaths[] = $defaultTransPath;
        }

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $keys = $this->providers->keys();
        $defaultProvider = 1 === \count($keys) ? $keys[0] : null;

        $this
            ->setDefinition([
                new InputArgument('provider', null !== $defaultProvider ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'The provider to push translations to.', $defaultProvider),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Override existing translations with local ones (it will delete not synchronized messages).'),
                new InputOption('delete-obsolete', null, InputOption::VALUE_NONE, 'Delete translations available on provider but not locally.'),
                new InputOption('domains', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the domains to push.'),
                new InputOption('locales', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the locales to push.', $this->enabledLocales),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format.', 'xlf'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version.', '1.2'),
            ])
            ->setDescription('Push translations to a given provider.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> push translations to the given provider. Only new
translations are pushed, existing ones are not overwritten.

You can overwrite existing translations:

  <info>php %command.full_name% --force provider</info>

You can delete provider translations which are not present locally:

  <info>php %command.full_name% --delete-obsolete provider</info>

Full example:

  <info>php %command.full_name% provider --force --delete-obsolete --domains=messages,validators --locales=en</info>

This command will push all translations linked to domains messages and validators
for the locale en. Provider translations for the specified domains and locale will
be erased if they're not present locally and overwritten if it's the
case. Provider translations for others domains and locales will be ignored.
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
            throw new InvalidArgumentException('You must defined framework.translator.enabled_locales config key in order to work with providers.');
        }

        $io = new SymfonyStyle($input, $output);

        $provider = $this->providers->get($input->getArgument('provider'));
        $domains = $input->getOption('domains');
        $locales = $input->getOption('locales');
        $force = $input->getOption('force');
        $deleteObsolete = $input->getOption('delete-obsolete');

        $localTranslations = $this->readLocalTranslations($locales, $domains, $this->transPaths);

        if (!$domains) {
            $domains = $localTranslations->getDomains();
        }

        if (!$deleteObsolete && $force) {
            $provider->write($localTranslations, true);

            return 0;
        }

        $providerTranslations = $provider->read($domains, $locales);

        if ($deleteObsolete) {
            $obsoleteMessages = $providerTranslations->diff($localTranslations);
            $provider->delete($obsoleteMessages);

            $io->success(sprintf(
                'Obsolete translations on %s are deleted (for [%s] locale(s), and [%s] domain(s)).',
                $provider,
                implode(', ', $locales),
                implode(', ', $domains)
            ));
        }

        $translationsToWrite = $localTranslations->diff($providerTranslations);

        if ($force) {
            $translationsToWrite->addBag($localTranslations->intersect($providerTranslations));
        }

        $provider->write($translationsToWrite);

        $io->success(sprintf(
            '%s local translations are sent to %s (for [%s] locale(s), and [%s] domain(s)).',
            $force ? 'All' : 'New',
            $input->getArgument('provider'),
            implode(', ', $locales),
            implode(', ', $domains)
        ));

        return 0;
    }
}
