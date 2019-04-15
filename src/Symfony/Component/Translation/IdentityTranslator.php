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

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IdentityTranslator implements LegacyTranslatorInterface, TranslatorInterface
{
    use TranslatorTrait {
        trans as private doTrans;
        setLocale as private doSetLocale;
    }

    private $selector;
    private $debug;

    /**
     * @param bool $debug
     */
    public function __construct($debug = true)
    {
        if (null === $debug || $debug instanceof MessageSelector) {
            $this->selector = $debug;
            $this->debug = true;
        } elseif (!\is_bool($debug)) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be iterable of %s, %s given.', __METHOD__, \gettype($debug)));
        } else {
            $this->debug = $debug;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->doTrans($id, $parameters, $domain, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->doSetLocale($locale);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.2, use the trans() method instead with a %count% parameter
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the trans() one instead with a "%%count%%" parameter.', __METHOD__), E_USER_DEPRECATED);

        if ($this->selector) {
            return strtr($this->selector->choose((string) $id, $number, $locale ?: $this->getLocale()), $parameters);
        }

        try {
            return $this->trans($id, ['%count%' => $number] + $parameters, $domain, $locale);
        } catch (\InvalidArgumentException $e) {
            if ($this->debug) {
                throw $e;
            }

            return strtr($id, $parameters);
        }
    }

    private function getPluralizationRule(int $number, string $locale): int
    {
        return PluralizationRules::get($number, $locale, false);
    }
}
