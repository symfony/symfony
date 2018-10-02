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

use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * IdentityTranslator does not translate anything.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IdentityTranslator implements TranslatorInterface
{
    use TranslatorTrait {
        transChoice as private doTransChoice;
    }

    private $selector;

    /**
     * @param MessageSelector|null $selector The message selector for pluralization
     */
    public function __construct(MessageSelector $selector = null)
    {
        $this->selector = $selector;

        if (__CLASS__ !== \get_class($this)) {
            @trigger_error(sprintf('Calling "%s()" is deprecated since Symfony 4.2.'), E_USER_DEPRECATED);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if ($this->selector) {
            return strtr($this->selector->choose((string) $id, (int) $number, $locale ?: $this->getLocale()), $parameters);
        }

        return $this->doTransChoice($id, $number, $parameters, $domain, $locale);
    }

    private function getPluralizationRule(int $number, string $locale): int
    {
        return PluralizationRules::get($number, $locale, false);
    }
}
