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
class WriteConfig
{
    private const DEFAULTS = [
        'file_format' => 'symfony_xliff',
        'update_translations' => '1',
    ];

    private function __construct(
        private array $options,
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

    /**
     * @return $this
     */
    public function setLocale(string $locale): static
    {
        $this->options['locale_id'] = $locale;

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
        $options = $dsn->getOptions()['write'] ?? [];

        unset($options['file_format'], $options['tags'], $options['locale_id'], $options['file']);

        $configOptions = array_merge(self::DEFAULTS, $options);

        return new self($configOptions);
    }
}
