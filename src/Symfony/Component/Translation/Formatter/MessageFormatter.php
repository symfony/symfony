<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class MessageFormatter implements MessageFormatterInterface, ChoiceMessageFormatterInterface
{
    private $translator;

    /**
     * @param TranslatorInterface|null $translator An identity translator to use as selector for pluralization
     */
    public function __construct($translator = null)
    {
        if ($translator instanceof MessageSelector) {
            $translator = new IdentityTranslator($translator);
        } elseif (null !== $translator && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }

        $this->translator = $translator ?? new IdentityTranslator();
    }

    /**
     * {@inheritdoc}
     */
    public function format($message, $locale, array $parameters = array())
    {
        return strtr($message, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function choiceFormat($message, $number, $locale, array $parameters = array())
    {
        $parameters = array_merge(array('%count%' => $number), $parameters);

        return $this->format($this->translator->transChoice($message, $number, array(), null, $locale), $locale, $parameters);
    }
}
