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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Helps finding unused or missing translation messages in a given locale
 * and comparing them with the fallback ones.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @final
 */
#[AsCommand(name: 'debug:translation', description: 'Display translation messages information')]
class TranslationDebugCommand extends Command
{
    public const EXIT_CODE_GENERAL_ERROR = 64;
    public const EXIT_CODE_MISSING = 65;
    public const EXIT_CODE_UNUSED = 66;
    public const EXIT_CODE_FALLBACK = 68;
    public const MESSAGE_MISSING = 0;
    public const MESSAGE_UNUSED = 1;
    public const MESSAGE_EQUALS_FALLBACK = 2;

    private $translator;
    private $reader;
    private $extractor;
    private ?string $defaultTransPath;
    private ?string $defaultViewsPath;
    private array $transPaths;
    private array $codePaths;
    private array $enabledLocales;

    public function __construct(TranslatorInterface $translator, TranslationReaderInterface $reader, ExtractorInterface $extractor, string $defaultTransPath = null, string $defaultViewsPath = null, array $transPaths = [], array $codePaths = [], array $enabledLocales = [])
    {
        parent::__construct();

        $this->translator = $translator;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
        $this->transPaths = $transPaths;
        $this->codePaths = $codePaths;
        $this->enabledLocales = $enabledLocales;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::OPTIONAL, 'The bundle name or directory where to load the messages'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'The messages domain'),
                new InputOption('only-missing', null, InputOption::VALUE_NONE, 'Display only missing messages'),
                new InputOption('only-unused', null, InputOption::VALUE_NONE, 'Display only unused messages'),
                new InputOption('all', null, InputOption::VALUE_NONE, 'Load messages from all registered bundles'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command helps finding unused or missing translation
messages and comparing them with the fallback ones by inspecting the
templates and translation files of a given bundle or the default translations directory.

You can display information about bundle translations in a specific locale:

  <info>php %command.full_name% en AcmeDemoBundle</info>

You can also specify a translation domain for the search:

  <info>php %command.full_name% --domain=messages en AcmeDemoBundle</info>

You can only display missing messages:

  <info>php %command.full_name% --only-missing en AcmeDemoBundle</info>

You can only display unused messages:

  <info>php %command.full_name% --only-unused en AcmeDemoBundle</info>

You can display information about application translations in a specific locale:

  <info>php %command.full_name% en</info>

You can display information about translations in all registered bundles in a specific locale:

  <info>php %command.full_name% --all en</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');

        $exitCode = self::SUCCESS;

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        // Define Root Paths
        $transPaths = $this->getRootTransPaths();
        $codePaths = $this->getRootCodePaths($kernel);

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $bundle = $kernel->getBundle($input->getArgument('bundle'));
                $bundleDir = $bundle->getPath();
                $transPaths = [is_dir($bundleDir.'/Resources/translations') ? $bundleDir.'/Resources/translations' : $bundleDir.'/translations'];
                $codePaths = [is_dir($bundleDir.'/Resources/views') ? $bundleDir.'/Resources/views' : $bundleDir.'/templates'];
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath;
                }
                if ($this->defaultViewsPath) {
                    $codePaths[] = $this->defaultViewsPath;
                }
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $path = $input->getArgument('bundle');

                $transPaths = [$path.'/translations'];
                $codePaths = [$path.'/templates'];

                if (!is_dir($transPaths[0])) {
                    throw new InvalidArgumentException(sprintf('"%s" is neither an enabled bundle nor a directory.', $transPaths[0]));
                }
            }
        } elseif ($input->getOption('all')) {
            foreach ($kernel->getBundles() as $bundle) {
                $bundleDir = $bundle->getPath();
                $transPaths[] = is_dir($bundleDir.'/Resources/translations') ? $bundleDir.'/Resources/translations' : $bundle->getPath().'/translations';
                $codePaths[] = is_dir($bundleDir.'/Resources/views') ? $bundleDir.'/Resources/views' : $bundle->getPath().'/templates';
            }
        }

        // Extract used messages
        $extractedCatalogue = $this->extractMessages($locale, $codePaths);

        // Load defined messages
        $currentCatalogue = $this->loadCurrentMessages($locale, $transPaths);

        // Merge defined and extracted messages to get all message ids
        $mergeOperation = new MergeOperation($extractedCatalogue, $currentCatalogue);
        $allMessages = $mergeOperation->getResult()->all($domain);
        if (null !== $domain) {
            $allMessages = [$domain => $allMessages];
        }

        // No defined or extracted messages
        if (empty($allMessages) || null !== $domain && empty($allMessages[$domain])) {
            $outputMessage = sprintf('No defined or extracted messages for locale "%s"', $locale);

            if (null !== $domain) {
                $outputMessage .= sprintf(' and domain "%s"', $domain);
            }

            $io->getErrorStyle()->warning($outputMessage);

            return self::EXIT_CODE_GENERAL_ERROR;
        }

        // Load the fallback catalogues
        $fallbackCatalogues = $this->loadFallbackCatalogues($locale, $transPaths);

        // Display header line
        $headers = ['State', 'Domain', 'Id', sprintf('Message Preview (%s)', $locale)];
        foreach ($fallbackCatalogues as $fallbackCatalogue) {
            $headers[] = sprintf('Fallback Message Preview (%s)', $fallbackCatalogue->getLocale());
        }
        $rows = [];
        // Iterate all message ids and determine their state
        foreach ($allMessages as $domain => $messages) {
            foreach (array_keys($messages) as $messageId) {
                $value = $currentCatalogue->get($messageId, $domain);
                $states = [];

                if ($extractedCatalogue->defines($messageId, $domain)) {
                    if (!$currentCatalogue->defines($messageId, $domain)) {
                        $states[] = self::MESSAGE_MISSING;

                        if (!$input->getOption('only-unused')) {
                            $exitCode = $exitCode | self::EXIT_CODE_MISSING;
                        }
                    }
                } elseif ($currentCatalogue->defines($messageId, $domain)) {
                    $states[] = self::MESSAGE_UNUSED;

                    if (!$input->getOption('only-missing')) {
                        $exitCode = $exitCode | self::EXIT_CODE_UNUSED;
                    }
                }

                if (!\in_array(self::MESSAGE_UNUSED, $states) && $input->getOption('only-unused')
                    || !\in_array(self::MESSAGE_MISSING, $states) && $input->getOption('only-missing')
                ) {
                    continue;
                }

                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    if ($fallbackCatalogue->defines($messageId, $domain) && $value === $fallbackCatalogue->get($messageId, $domain)) {
                        $states[] = self::MESSAGE_EQUALS_FALLBACK;

                        $exitCode = $exitCode | self::EXIT_CODE_FALLBACK;

                        break;
                    }
                }

                $row = [$this->formatStates($states), $domain, $this->formatId($messageId), $this->sanitizeString($value)];
                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    $row[] = $this->sanitizeString($fallbackCatalogue->get($messageId, $domain));
                }

                $rows[] = $row;
            }
        }

        $io->table($headers, $rows);

        return $exitCode;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('locale')) {
            $suggestions->suggestValues($this->enabledLocales);

            return;
        }

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        if ($input->mustSuggestArgumentValuesFor('bundle')) {
            $availableBundles = [];
            foreach ($kernel->getBundles() as $bundle) {
                $availableBundles[] = $bundle->getName();

                if ($extension = $bundle->getContainerExtension()) {
                    $availableBundles[] = $extension->getAlias();
                }
            }

            $suggestions->suggestValues($availableBundles);

            return;
        }

        if ($input->mustSuggestOptionValuesFor('domain')) {
            $locale = $input->getArgument('locale');

            $mergeOperation = new MergeOperation(
                $this->extractMessages($locale, $this->getRootCodePaths($kernel)),
                $this->loadCurrentMessages($locale, $this->getRootTransPaths())
            );

            $suggestions->suggestValues($mergeOperation->getDomains());
        }
    }

    private function formatState(int $state): string
    {
        if (self::MESSAGE_MISSING === $state) {
            return '<error> missing </error>';
        }

        if (self::MESSAGE_UNUSED === $state) {
            return '<comment> unused </comment>';
        }

        if (self::MESSAGE_EQUALS_FALLBACK === $state) {
            return '<info> fallback </info>';
        }

        return $state;
    }

    private function formatStates(array $states): string
    {
        $result = [];
        foreach ($states as $state) {
            $result[] = $this->formatState($state);
        }

        return implode(' ', $result);
    }

    private function formatId(string $id): string
    {
        return sprintf('<fg=cyan;options=bold>%s</>', $id);
    }

    private function sanitizeString(string $string, int $length = 40): string
    {
        $string = trim(preg_replace('/\s+/', ' ', $string));

        if (false !== $encoding = mb_detect_encoding($string, null, true)) {
            if (mb_strlen($string, $encoding) > $length) {
                return mb_substr($string, 0, $length - 3, $encoding).'...';
            }
        } elseif (\strlen($string) > $length) {
            return substr($string, 0, $length - 3).'...';
        }

        return $string;
    }

    private function extractMessages(string $locale, array $transPaths): MessageCatalogue
    {
        $extractedCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        return $extractedCatalogue;
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

    /**
     * @return MessageCatalogue[]
     */
    private function loadFallbackCatalogues(string $locale, array $transPaths): array
    {
        $fallbackCatalogues = [];
        if ($this->translator instanceof Translator || $this->translator instanceof DataCollectorTranslator || $this->translator instanceof LoggingTranslator) {
            foreach ($this->translator->getFallbackLocales() as $fallbackLocale) {
                if ($fallbackLocale === $locale) {
                    continue;
                }

                $fallbackCatalogue = new MessageCatalogue($fallbackLocale);
                foreach ($transPaths as $path) {
                    if (is_dir($path)) {
                        $this->reader->read($path, $fallbackCatalogue);
                    }
                }
                $fallbackCatalogues[] = $fallbackCatalogue;
            }
        }

        return $fallbackCatalogues;
    }

    private function getRootTransPaths(): array
    {
        $transPaths = $this->transPaths;
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }

        return $transPaths;
    }

    private function getRootCodePaths(KernelInterface $kernel): array
    {
        $codePaths = $this->codePaths;
        $codePaths[] = $kernel->getProjectDir().'/src';
        if ($this->defaultViewsPath) {
            $codePaths[] = $this->defaultViewsPath;
        }

        return $codePaths;
    }
}
