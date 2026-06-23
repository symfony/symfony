<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "I18N" tab of the interactive "debug" command.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class TranslationDebugSection extends AbstractDebugSection
{
    private const int MESSAGE_MISSING = 0;
    private const int MESSAGE_UNUSED = 1;
    private const int MESSAGE_EQUALS_FALLBACK = 2;

    /** @var array<string, array{extracted: MessageCatalogue, current: MessageCatalogue, fallbacks: list<MessageCatalogue>}> */
    private array $catalogues = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $extractedMessages = null;

    /** @var array<string, MessageCatalogue> */
    private array $extractedCatalogues = [];

    /** @var array<string, MessageCatalogue> */
    private array $currentCatalogues = [];

    /** @var array<string, array{domain: string, id: string, translations: array<string, array{defined: bool, value: string, states: list<int>, fallbacks: array<string, string>}>}>|null */
    private ?array $messages = null;

    /** @var array<string, int>|null */
    private ?array $localeMessageCounts = null;

    /** @var list<string> */
    private readonly array $enabledLocales;

    /**
     * @param list<string> $transPaths
     * @param list<string> $codePaths
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly TranslationReaderInterface $reader,
        private readonly ExtractorInterface $extractor,
        private readonly ?string $defaultTransPath = null,
        private readonly ?string $defaultViewsPath = null,
        private readonly array $transPaths = [],
        private readonly array $codePaths = [],
        array $enabledLocales = [],
        private readonly ?KernelInterface $kernel = null,
    ) {
        $this->enabledLocales = array_values(array_filter($enabledLocales));
    }

    public function getLabel(): string
    {
        return 'I18N';
    }

    public function getShortLabel(): string
    {
        return 'I18N';
    }

    public function describe(DebugItem $item, int $width): string
    {
        if (null !== $item->detail) {
            return $item->detail;
        }

        return \sprintf('I18N item "%s" does not exist.', $item->value);
    }

    /**
     * Extracting and reading the catalogues of every enabled locale is costly,
     * so the precomputed detail is baked into each item as it is built.
     */
    protected function buildItems(): array
    {
        $messages = $this->getMessages();
        $localeMessageCounts = $this->getLocaleMessageCounts();
        $domainMessageCounts = $this->getDomainMessageCounts($messages);

        $items = [];
        foreach ($domainMessageCounts as $domain => $count) {
            $domainMessages = array_filter($messages, static fn (array $message): bool => $message['domain'] === $domain);
            $detail = $this->renderAllDetail($domainMessages);
            $label = \sprintf('%s (%d)', $domain, $count);

            $items[] = new DebugItem('domain', $domain, $label, group: 'Domains', detail: $detail, searchText: $detail);
        }

        $allDetail = $this->renderAllDetail($messages);
        $items[] = new DebugItem('all', 'all', \sprintf('All (%d)', \count($messages)), group: 'Contents', detail: $allDetail, searchText: $allDetail);

        foreach ($this->enabledLocales as $locale) {
            $detail = $this->renderLocaleDetail($locale, $messages);
            $label = \sprintf('%s (%d)', $this->getLocaleName($locale), $localeMessageCounts[$locale] ?? 0);

            $items[] = new DebugItem('locale', $locale, $label, group: 'Contents', detail: $detail, searchText: $detail);
        }

        return $items;
    }

    /**
     * @return array<string, array{domain: string, id: string, translations: array<string, array{defined: bool, value: string, states: list<int>, fallbacks: array<string, string>}>}>
     */
    private function getMessages(): array
    {
        if (null !== $this->messages) {
            return $this->messages;
        }

        $messages = [];
        $localeMessageCounts = [];

        foreach ($this->enabledLocales as $locale) {
            $catalogues = $this->getCatalogues($locale);
            $mergeOperation = new MergeOperation($catalogues['extracted'], $catalogues['current']);
            $localeMessageCounts[$locale] = 0;

            foreach ($mergeOperation->getResult()->all() as $domain => $domainMessages) {
                foreach (array_keys($domainMessages) as $id) {
                    $key = $domain.self::VALUE_SEPARATOR.$id;
                    $messages[$key] ??= [
                        'domain' => $domain,
                        'id' => $id,
                        'translations' => [],
                    ];
                    ++$localeMessageCounts[$locale];

                    $messages[$key]['translations'][$locale] = [
                        'defined' => $catalogues['current']->defines($id, $domain),
                        'value' => $catalogues['current']->defines($id, $domain) ? $catalogues['current']->get($id, $domain) : '',
                        'states' => $this->computeStates($catalogues, $domain, $id),
                        'fallbacks' => $this->getFallbackValues($catalogues['fallbacks'], $domain, $id),
                    ];
                }
            }
        }

        uasort($messages, static fn (array $a, array $b): int => [$a['domain'], $a['id']] <=> [$b['domain'], $b['id']]);

        $this->localeMessageCounts = $localeMessageCounts;

        return $this->messages = $messages;
    }

    /**
     * @return array<string, int>
     */
    private function getLocaleMessageCounts(): array
    {
        if (null === $this->localeMessageCounts) {
            $this->getMessages();
        }

        return $this->localeMessageCounts ?? [];
    }

    /**
     * @param array<string, array{domain: string, id: string, translations: array<string, array{defined: bool, value: string, states: list<int>, fallbacks: array<string, string>}>}> $messages
     *
     * @return array<string, int>
     */
    private function getDomainMessageCounts(array $messages): array
    {
        $domainMessageCounts = [];
        foreach ($messages as $message) {
            $domainMessageCounts[$message['domain']] = ($domainMessageCounts[$message['domain']] ?? 0) + 1;
        }

        ksort($domainMessageCounts);

        return $domainMessageCounts;
    }

    /**
     * @param array<string, array{domain: string, id: string, translations: array<string, array{defined: bool, value: string, states: list<int>, fallbacks: array<string, string>}>}> $messages
     */
    private function renderAllDetail(array $messages): string
    {
        if (!$messages) {
            return "No translations were found.\n";
        }

        $lines = [];
        foreach ($messages as $message) {
            $lines[] = \sprintf("\e[33m%s\e[39m", $message['id']);
            $lines[] = \sprintf('  domain: %s', $message['domain']);

            foreach ($this->enabledLocales as $locale) {
                $translation = $message['translations'][$locale] ?? null;
                if (!$translation || !$translation['defined']) {
                    continue;
                }

                $lines[] = \sprintf('  %s: %s', $this->getLocaleName($locale), $this->indentValue($translation['value'], 4));
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, array{domain: string, id: string, translations: array<string, array{defined: bool, value: string, states: list<int>, fallbacks: array<string, string>}>}> $messages
     */
    private function renderLocaleDetail(string $locale, array $messages): string
    {
        $lines = [
            \sprintf("\e[33m%s\e[39m", $this->getLocaleName($locale)),
            str_repeat('=', \strlen($this->getLocaleName($locale))),
            '',
        ];

        foreach ($messages as $message) {
            $translation = $message['translations'][$locale] ?? null;
            if (!$translation) {
                continue;
            }

            $lines[] = \sprintf("\e[33m%s\e[39m", $message['id']);
            $lines[] = \sprintf('  domain: %s', $message['domain']);
            $lines[] = \sprintf('  state: %s', '' !== ($states = $this->formatStates($translation['states'])) ? $states : 'defined');
            $lines[] = \sprintf('  translation: %s', $translation['defined'] ? $this->indentValue($translation['value'], 4) : '<missing>');

            foreach ($translation['fallbacks'] as $fallbackLocale => $fallbackValue) {
                $lines[] = \sprintf('  fallback (%s): %s', $this->getLocaleName($fallbackLocale), $this->indentValue($fallbackValue, 4));
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{extracted: MessageCatalogue, current: MessageCatalogue, fallbacks: list<MessageCatalogue>}
     */
    private function getCatalogues(string $locale): array
    {
        if (isset($this->catalogues[$locale])) {
            return $this->catalogues[$locale];
        }

        $transPaths = $this->getRootTransPaths();
        $codePaths = $this->getRootCodePaths();

        return $this->catalogues[$locale] = [
            'extracted' => $this->extractMessages($locale, $codePaths),
            'current' => $this->loadCurrentMessages($locale, $transPaths),
            'fallbacks' => $this->loadFallbackCatalogues($locale, $transPaths),
        ];
    }

    /**
     * @param array{extracted: MessageCatalogue, current: MessageCatalogue, fallbacks: list<MessageCatalogue>} $catalogues
     *
     * @return list<int>
     */
    private function computeStates(array $catalogues, string $domain, string $id): array
    {
        $states = [];
        $defined = $catalogues['current']->defines($id, $domain);
        $value = $defined ? $catalogues['current']->get($id, $domain) : null;

        if ($catalogues['extracted']->defines($id, $domain)) {
            if (!$defined) {
                $states[] = self::MESSAGE_MISSING;
            }
        } elseif ($defined) {
            $states[] = self::MESSAGE_UNUSED;
        }

        if (null !== $value) {
            foreach ($catalogues['fallbacks'] as $fallbackCatalogue) {
                if ($fallbackCatalogue->defines($id, $domain) && $value === $fallbackCatalogue->get($id, $domain)) {
                    $states[] = self::MESSAGE_EQUALS_FALLBACK;

                    break;
                }
            }
        }

        return $states;
    }

    /**
     * @param list<MessageCatalogue> $fallbackCatalogues
     *
     * @return array<string, string>
     */
    private function getFallbackValues(array $fallbackCatalogues, string $domain, string $id): array
    {
        $values = [];
        foreach ($fallbackCatalogues as $fallbackCatalogue) {
            if ($fallbackCatalogue->defines($id, $domain)) {
                $values[$fallbackCatalogue->getLocale()] = $fallbackCatalogue->get($id, $domain);
            }
        }

        return $values;
    }

    /**
     * @param list<int> $states
     */
    private function formatStates(array $states): string
    {
        $result = [];
        foreach ($states as $state) {
            $result[] = match ($state) {
                self::MESSAGE_MISSING => 'missing',
                self::MESSAGE_UNUSED => 'unused',
                self::MESSAGE_EQUALS_FALLBACK => 'same as fallback',
                default => (string) $state,
            };
        }

        return implode(', ', $result);
    }

    /**
     * @param list<string> $codePaths
     */
    private function extractMessages(string $locale, array $codePaths): MessageCatalogue
    {
        if (isset($this->extractedCatalogues[$locale])) {
            return $this->extractedCatalogues[$locale];
        }

        if (null === $this->extractedMessages) {
            $extractedCatalogue = new MessageCatalogue($locale);
            foreach ($codePaths as $path) {
                if (is_dir($path) || is_file($path)) {
                    $this->extractor->extract($path, $extractedCatalogue);
                }
            }

            $this->extractedMessages = $extractedCatalogue->all();
        }

        $extractedCatalogue = new MessageCatalogue($locale);
        foreach ($this->extractedMessages as $domain => $messages) {
            $extractedCatalogue->add($messages, $domain);
        }

        return $this->extractedCatalogues[$locale] = $extractedCatalogue;
    }

    /**
     * @param list<string> $transPaths
     */
    private function loadCurrentMessages(string $locale, array $transPaths): MessageCatalogue
    {
        if (isset($this->currentCatalogues[$locale])) {
            return $this->currentCatalogues[$locale];
        }

        $currentCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

        return $this->currentCatalogues[$locale] = $currentCatalogue;
    }

    /**
     * @param list<string> $transPaths
     *
     * @return list<MessageCatalogue>
     */
    private function loadFallbackCatalogues(string $locale, array $transPaths): array
    {
        $fallbackCatalogues = [];
        if ($this->translator instanceof Translator || $this->translator instanceof DataCollectorTranslator || $this->translator instanceof LoggingTranslator) {
            foreach ($this->translator->getFallbackLocales() as $fallbackLocale) {
                if ($fallbackLocale === $locale) {
                    continue;
                }

                $fallbackCatalogues[] = $this->loadCurrentMessages($fallbackLocale, $transPaths);
            }
        }

        return $fallbackCatalogues;
    }

    /**
     * @return list<string>
     */
    private function getRootTransPaths(): array
    {
        $transPaths = $this->transPaths;
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }

        return $transPaths;
    }

    /**
     * @return list<string>
     */
    private function getRootCodePaths(): array
    {
        $codePaths = $this->codePaths;
        if ($this->kernel) {
            $codePaths[] = $this->kernel->getProjectDir().'/src';
        }
        if ($this->defaultViewsPath) {
            $codePaths[] = $this->defaultViewsPath;
        }

        return $codePaths;
    }

    private function getLocaleName(string $locale): string
    {
        if (class_exists(Languages::class)) {
            try {
                return Languages::getName(str_replace('-', '_', $locale), 'en');
            } catch (\Throwable) {
            }
        }

        return $locale;
    }

    private function indentValue(string $value, int $indent): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (!str_contains($value, "\n")) {
            return $value;
        }

        return "\n".str_repeat(' ', $indent).str_replace("\n", "\n".str_repeat(' ', $indent), $value);
    }
}
