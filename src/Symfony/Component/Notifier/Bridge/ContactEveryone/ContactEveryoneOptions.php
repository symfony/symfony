<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 *
 * @see https://ceo-be.multimediabs.com/attachments/hosted/lightApiManualsFR
 */
final class ContactEveryoneOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function diffusionName(string $diffusionName): static
    {
        $this->options['diffusionname'] = $diffusionName;

        return $this;
    }

    /**
     * @return $this
     */
    public function category(string $category): static
    {
        $this->options['category'] = $category;

        return $this;
    }

    /**
     * @param 'fr_FR'|'en_GB' $locale
     *
     * @return $this
     */
    public function locale(string $locale): static
    {
        $this->options['locale'] = $locale;

        return $this;
    }

    /**
     * @return $this
     */
    public function unicode(bool $unicode): static
    {
        $this->options['xcharset'] = $unicode ? 'true' : 'false';

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
