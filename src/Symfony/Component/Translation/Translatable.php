<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Nate Wiebe <nate@northern.co>
 */
final class Translatable
{
    private $message;
    private $parameters;
    private $domain;

    public function __construct(string $message, array $parameters = [], string $domain = 'messages')
    {
        $this->message = $message;
        $this->parameters = $parameters;
        $this->domain = $domain;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public static function trans(TranslatorInterface $translator, self $translatable, ?string $locale = null): string
    {
        return $translator->trans($translatable->getMessage(), $translatable->getParameters(), $translatable->getDomain(), $locale);
    }
}
