<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

@trigger_error('The '.TranslatorHelper::class.' class is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', E_USER_DEPRECATED);

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class TranslatorHelper extends Helper
{
    use TranslatorTrait {
        getLocale as private;
        setLocale as private;
        trans as private doTrans;
    }

    protected $translator;

    /**
     * @param TranslatorInterface|null $translator
     */
    public function __construct($translator = null)
    {
        if (null !== $translator && !$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
        $this->translator = $translator;
    }

    /**
     * @see TranslatorInterface::trans()
     */
    public function trans($id, array $parameters = [], $domain = 'messages', $locale = null)
    {
        if (null === $this->translator) {
            return $this->doTrans($id, $parameters, $domain, $locale);
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @see TranslatorInterface::transChoice()
     * @deprecated since Symfony 4.2, use the trans() method instead with a %count% parameter
     */
    public function transChoice($id, $number, array $parameters = [], $domain = 'messages', $locale = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the trans() one instead with a "%%count%%" parameter.', __METHOD__), E_USER_DEPRECATED);

        if (null === $this->translator) {
            return $this->doTrans($id, ['%count%' => $number] + $parameters, $domain, $locale);
        }
        if ($this->translator instanceof TranslatorInterface) {
            return $this->translator->trans($id, ['%count%' => $number] + $parameters, $domain, $locale);
        }

        return $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'translator';
    }
}
