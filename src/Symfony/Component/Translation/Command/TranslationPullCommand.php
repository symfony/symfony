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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
#[AsCommand(name: 'translation:pull', description: 'Pull translations from a given provider.')]
final class TranslationPullCommand extends Command
{
    use TranslationTrait;

    public function __construct(
        private TranslationProviderCollection $providerCollection,
        private TranslationWriterInterface $writer,
        private TranslationReaderInterface $reader,
        private string $defaultLocale,
        private array $transPaths = [],
        private array $enabledLocales = [],
    ) {
        $this->enabledLocales = array_filter($enabledLocales);
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('provider')) {
            $suggestions->suggestValues($this->providerCollection->keys());

            return;
        }

        if ($input->mustSuggestOptionValuesFor('domains')) {
            $provider = $this->providerCollection->get($input->getArgument('provider'));

            if (method_exists($provider, 'getDomains')) {
                $suggestions->suggestValues($provider->getDomains());
            }

            return;
        }

        if ($input->mustSuggestOptionValuesFor('locales')) {
            $suggestions->suggestValues($this->enabledLocales);

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(['php', 'xlf', 'xlf12', 'xlf20', 'po', 'mo', 'yml', 'yaml', 'ts', 'csv', 'json', 'ini', 'res']);
        }
    }

    protected function configure(): void
    {
        $keys = $this->providerCollection->keys();
        $defaultProvider = 1 === \count($keys) ? $keys[0] : null;

        $this
            ->setDefinition([
                new InputArgument('provider', null !== $defaultProvider ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'The provider to pull translations from.', $defaultProvider),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Override existing translations with provider ones.'),
                new InputOption('delete-missing', null, InputOption::VALUE_NONE, 'Delete local translations that are not on the provider.'),
                new InputOption('intl-icu', null, InputOption::VALUE_NONE, 'Associated to --force option, it will write messages in "%domain%+intl-icu.%locale%.xlf" files.'),
                new InputOption('domains', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specify the domains to pull.'),
                new InputOption('locales', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specify the locales to pull.'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Override the default output format.', 'xlf12'),
                new InputOption('as-tree', null, InputOption::VALUE_REQUIRED, 'Write messages as a tree-like structure. Needs --format=yaml. The given value defines the level where to switch to inline YAML'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</> command pulls translations from the given provider. Only
                new translations are pulled, existing ones are not overwritten.

                To overwrite existing local translations while preserving keys not on the provider, use <info>--force</>:

                  <info>%command.invocation% provider --force</>

                To delete local translations that are missing from the provider, use <info>--delete-missing</>:

                  <info>%command.invocation% provider --delete-missing</>

                Both options can be combined to replace the local catalogue completely:

                  <info>%command.invocation% provider --force --delete-missing --domains=messages --locales=en</>

                Other domains and locales are not affected.

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $provider = $this->providerCollection->get($input->getArgument('provider'));
        $force = $input->getOption('force');
        $deleteMissing = $input->getOption('delete-missing');
        $intlIcu = $input->getOption('intl-icu');
        $locales = $input->getOption('locales') ?: $this->enabledLocales;
        $domains = $input->getOption('domains');
        $format = $input->getOption('format');
        $asTree = (int) $input->getOption('as-tree');
        $xliffVersion = '1.2';

        if ($intlIcu && !$force) {
            $io->note('--intl-icu option only has an effect when used with --force. Here, it will be ignored.');
        }

        switch ($format) {
            case 'xlf20': $xliffVersion = '2.0';
                // no break
            case 'xlf12': $format = 'xlf';
        }

        $writeOptions = [
            'path' => end($this->transPaths),
            'xliff_version' => $xliffVersion,
            'default_locale' => $this->defaultLocale,
            'as_tree' => (bool) $asTree,
            'inline' => $asTree,
        ];

        if (!$domains) {
            $domains = $provider->getDomains();
        }

        $providerTranslations = $provider->read($domains, $locales);

        if ($force && $deleteMissing) {
            // Replace the selected catalogues with the provider's messages.
            foreach ($providerTranslations->getCatalogues() as $catalogue) {
                $operation = new TargetOperation(new MessageCatalogue($catalogue->getLocale()), $catalogue);
                if ($intlIcu) {
                    $operation->moveMessagesToIntlDomainsIfPossible();
                }
                $this->writer->write($operation->getResult(), $format, $writeOptions);
            }
        } else {
            $localTranslations = $this->readLocalTranslations($locales, $domains, $this->transPaths);

            if ($deleteMissing) {
                // Keep only provider keys, but preserve local values unless --force is set.
                foreach ($providerTranslations->getCatalogues() as $catalogue) {
                    $result = new MessageCatalogue($catalogue->getLocale());
                    $result->addCatalogue($catalogue);
                    $localCatalogue = $localTranslations->getCatalogue($catalogue->getLocale());
                    foreach ($result->all() as $domain => $messages) {
                        foreach ($messages as $id => $message) {
                            if ($localCatalogue->defines($id, $domain)) {
                                $result->set($id, $localCatalogue->get($id, $domain), $domain);
                            }
                        }
                    }
                    $this->writer->write($result, $format, $writeOptions);
                }
            } else {
                if ($force) {
                    // Preserve local-only keys while using the provider's values for shared keys.
                    foreach ($providerTranslations->getCatalogues() as $catalogue) {
                        $localCatalogue = $localTranslations->getCatalogue($catalogue->getLocale());
                        $localCatalogue->addCatalogue($catalogue);
                    }
                } else {
                    // Append only translations that do not already exist locally.
                    $localTranslations->addBag($providerTranslations->diff($localTranslations));
                }

                foreach ($localTranslations->getCatalogues() as $catalogue) {
                    if ($force && $intlIcu) {
                        $operation = new TargetOperation(new MessageCatalogue($catalogue->getLocale()), $catalogue);
                        $operation->moveMessagesToIntlDomainsIfPossible();
                        $catalogue = $operation->getResult();
                    }
                    $this->writer->write($catalogue, $format, $writeOptions);
                }
            }
        }

        if ($force || $deleteMissing) {
            $io->success(\sprintf('Local translations has been updated from "%s" (for "%s" locale(s), and "%s" domain(s)).', parse_url($provider, \PHP_URL_SCHEME), implode(', ', $locales), implode(', ', $domains)));
        } else {
            $io->success(\sprintf('New translations from "%s" has been written locally (for "%s" locale(s), and "%s" domain(s)).', parse_url($provider, \PHP_URL_SCHEME), implode(', ', $locales), implode(', ', $domains)));
        }

        return 0;
    }
}
