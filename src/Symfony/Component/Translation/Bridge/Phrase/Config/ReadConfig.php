<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Phrase\Config;

use Symfony\Component\Translation\Provider\Dsn;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class ReadConfig
{
    private const DEFAULTS = [
        'file_format' => 'symfony_xliff',
        'include_empty_translations' => '1',
        'tags' => [],
        'format_options' => [
            'enclose_in_cdata' => '1',
        ],
    ];

    private function __construct(
        private array $options,
        private readonly bool $fallbackEnabled
    ) {
    }

    /**
     * @return $this
     */
    public function setTag(string $tag): static
    {
        $this->options['tags'] = $tag;

        return $this;
    }

    public function isFallbackLocaleEnabled(): bool
    {
        return $this->fallbackEnabled;
    }

    /**
     * @return $this
     */
    public function setFallbackLocale(string $locale): static
    {
        $this->options['fallback_locale_id'] = $locale;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return $this
     */
    public static function fromDsn(Dsn $dsn): static
    {
        $options = $dsn->getOptions()['read'] ?? [];

        // enforce empty translations when fallback locale is enabled
        if (true === $fallbackLocale = filter_var($options['fallback_locale_enabled'] ?? false, \FILTER_VALIDATE_BOOL)) {
            $options['include_empty_translations'] = '1';
        }

        unset($options['file_format'], $options['tags'], $options['tag'], $options['fallback_locale_id'], $options['fallback_locale_enabled']);

        $options['format_options'] = array_merge(self::DEFAULTS['format_options'], $options['format_options'] ?? []);

        $configOptions = array_merge(self::DEFAULTS, $options);

        return new self($configOptions, $fallbackLocale);
    }
}
