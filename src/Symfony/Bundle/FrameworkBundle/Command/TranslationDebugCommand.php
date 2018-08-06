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
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Helps finding unused or missing translation messages in a given locale
 * and comparing them with the fallback ones.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @final
 */
class TranslationDebugCommand extends Command
{
    const MESSAGE_MISSING = 0;
    const MESSAGE_UNUSED = 1;
    const MESSAGE_EQUALS_FALLBACK = 2;

    protected static $defaultName = 'debug:translation';

    private $translator;
    private $reader;
    private $extractor;
    private $defaultTransPath;
    private $defaultViewsPath;

    public function __construct(TranslatorInterface $translator, TranslationReaderInterface $reader, ExtractorInterface $extractor, string $defaultTransPath = null, string $defaultViewsPath = null)
    {
        parent::__construct();

        $this->translator = $translator;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::OPTIONAL, 'The bundle name or directory where to load the messages, defaults to app/Resources folder'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'The messages domain'),
                new InputOption('only-missing', null, InputOption::VALUE_NONE, 'Displays only missing messages'),
                new InputOption('only-unused', null, InputOption::VALUE_NONE, 'Displays only unused messages'),
                new InputOption('all', null, InputOption::VALUE_NONE, 'Load messages from all registered bundles'),
            ))
            ->setDescription('Displays translation messages information')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command helps finding unused or missing translation
messages and comparing them with the fallback ones by inspecting the
templates and translation files of a given bundle or the app folder.

You can display information about bundle translations in a specific locale:

  <info>php %command.full_name% en AcmeDemoBundle</info>

You can also specify a translation domain for the search:

  <info>php %command.full_name% --domain=messages en AcmeDemoBundle</info>

You can only display missing messages:

  <info>php %command.full_name% --only-missing en AcmeDemoBundle</info>

You can only display unused messages:

  <info>php %command.full_name% --only-unused en AcmeDemoBundle</info>

You can display information about app translations in a specific locale:

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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        // Define Root Paths
        $transPaths = array($kernel->getRootDir().'/Resources/translations');
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }
        $viewsPaths = array($kernel->getRootDir().'/Resources/views');
        if ($this->defaultViewsPath) {
            $viewsPaths[] = $this->defaultViewsPath;
        }

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $bundle = $kernel->getBundle($input->getArgument('bundle'));
                $transPaths = array($bundle->getPath().'/Resources/translations');
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath.'/'.$bundle->getName();
                }
                $transPaths[] = sprintf('%s/Resources/%s/translations', $kernel->getRootDir(), $bundle->getName());
                $viewsPaths = array($bundle->getPath().'/Resources/views');
                if ($this->defaultViewsPath) {
                    $viewsPaths[] = $this->defaultViewsPath.'/bundles/'.$bundle->getName();
                }
                $viewsPaths[] = sprintf('%s/Resources/%s/views', $kernel->getRootDir(), $bundle->getName());
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $transPaths = array($input->getArgument('bundle').'/Resources/translations');
                $viewsPaths = array($input->getArgument('bundle').'/Resources/views');

                if (!is_dir($transPaths[0])) {
                    throw new InvalidArgumentException(sprintf('"%s" is neither an enabled bundle nor a directory.', $transPaths[0]));
                }
            }
        } elseif ($input->getOption('all')) {
            foreach ($kernel->getBundles() as $bundle) {
                $transPaths[] = $bundle->getPath().'/Resources/translations';
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath.'/'.$bundle->getName();
                }
                $transPaths[] = sprintf('%s/Resources/%s/translations', $kernel->getRootDir(), $bundle->getName());
                $viewsPaths[] = $bundle->getPath().'/Resources/views';
                if ($this->defaultViewsPath) {
                    $viewsPaths[] = $this->defaultViewsPath.'/bundles/'.$bundle->getName();
                }
                $viewsPaths[] = sprintf('%s/Resources/%s/views', $kernel->getRootDir(), $bundle->getName());
            }
        }

        // Extract used messages
        $extractedCatalogue = $this->extractMessages($locale, $viewsPaths);

        // Load defined messages
        $currentCatalogue = $this->loadCurrentMessages($locale, $transPaths);

        // Merge defined and extracted messages to get all message ids
        $mergeOperation = new MergeOperation($extractedCatalogue, $currentCatalogue);
        $allMessages = $mergeOperation->getResult()->all($domain);
        if (null !== $domain) {
            $allMessages = array($domain => $allMessages);
        }

        // No defined or extracted messages
        if (empty($allMessages) || null !== $domain && empty($allMessages[$domain])) {
            $outputMessage = sprintf('No defined or extracted messages for locale "%s"', $locale);

            if (null !== $domain) {
                $outputMessage .= sprintf(' and domain "%s"', $domain);
            }

            $io->getErrorStyle()->warning($outputMessage);

            return;
        }

        // Load the fallback catalogues
        $fallbackCatalogues = $this->loadFallbackCatalogues($locale, $transPaths);

        // Display header line
        $headers = array('State', 'Domain', 'Id', sprintf('Message Preview (%s)', $locale));
        foreach ($fallbackCatalogues as $fallbackCatalogue) {
            $headers[] = sprintf('Fallback Message Preview (%s)', $fallbackCatalogue->getLocale());
        }
        $rows = array();
        // Iterate all message ids and determine their state
        foreach ($allMessages as $domain => $messages) {
            foreach (array_keys($messages) as $messageId) {
                $value = $currentCatalogue->get($messageId, $domain);
                $states = array();

                if ($extractedCatalogue->defines($messageId, $domain)) {
                    if (!$currentCatalogue->defines($messageId, $domain)) {
                        $states[] = self::MESSAGE_MISSING;
                    }
                } elseif ($currentCatalogue->defines($messageId, $domain)) {
                    $states[] = self::MESSAGE_UNUSED;
                }

                if (!\in_array(self::MESSAGE_UNUSED, $states) && true === $input->getOption('only-unused')
                    || !\in_array(self::MESSAGE_MISSING, $states) && true === $input->getOption('only-missing')) {
                    continue;
                }

                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    if ($fallbackCatalogue->defines($messageId, $domain) && $value === $fallbackCatalogue->get($messageId, $domain)) {
                        $states[] = self::MESSAGE_EQUALS_FALLBACK;

                        break;
                    }
                }

                $row = array($this->formatStates($states), $domain, $this->formatId($messageId), $this->sanitizeString($value));
                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    $row[] = $this->sanitizeString($fallbackCatalogue->get($messageId, $domain));
                }

                $rows[] = $row;
            }
        }

        $io->table($headers, $rows);
    }

    private function formatState($state): string
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
        $result = array();
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
            if (is_dir($path)) {
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
        $fallbackCatalogues = array();
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
}
