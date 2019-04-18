<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Util;

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal to be removed in Symfony 5.0.
 */
class LegacyTranslatorProxy implements LegacyTranslatorInterface, TranslatorInterface
{
    private $translator;

    /**
     * @param LegacyTranslatorInterface|TranslatorInterface $translator
     */
    public function __construct($translator)
    {
        if ($translator instanceof LegacyTranslatorInterface) {
            // no-op
        } elseif (!$translator instanceof TranslatorInterface) {
            throw new \InvalidArgumentException(sprintf('The translator passed to "%s()" must implement "%s" or "%s".', __METHOD__, TranslatorInterface::class, LegacyTranslatorInterface::class));
        } elseif (!$translator instanceof LocaleAwareInterface) {
            throw new \InvalidArgumentException(sprintf('The translator passed to "%s()" must implement "%s".', __METHOD__, LocaleAwareInterface::class));
        }

        $this->translator = $translator;
    }

    /**
     * @return LegacyTranslatorInterface|TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->translator->trans($id, ['%count%' => $number] + $parameters, $domain, $locale);
    }
}
