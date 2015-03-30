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

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class DataCollectorTranslator implements TranslatorInterface, TranslatorBagInterface
{
    const MESSAGE_DEFINED = 0;
    const MESSAGE_MISSING = 1;
    const MESSAGE_EQUALS_FALLBACK = 2;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $messages = array();

    /**
     * @param Translator $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        if (!($translator instanceof TranslatorInterface && $translator instanceof TranslatorBagInterface)) {
            throw new \InvalidArgumentException(sprintf('The Translator "%s" must implement TranslatorInterface and TranslatorBagInterface.', get_class($translator)));
        }

        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $trans = $this->translator->trans($id, $parameters, $domain, $locale);
        $this->collectMessage($locale, $domain, $id, $trans);

        return $trans;
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        $trans = $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
        $this->collectMessage($locale, $domain, $id, $trans);

        return $trans;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * Passes through all unknown calls onto the translator object.
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->translator, $method), $args);
    }

    /**
     * @return array
     */
    public function getCollectedMessages()
    {
        return $this->messages;
    }

    /**
     * @param string|null $locale
     * @param string|null $domain
     * @param string      $id
     * @param string      $trans
     */
    private function collectMessage($locale, $domain, $id, $translation)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        $id = (string) $id;
        $catalogue = $this->translator->getCatalogue($locale);
        if ($catalogue->defines($id, $domain)) {
            $state = self::MESSAGE_DEFINED;
        } elseif ($catalogue->has($id, $domain)) {
            $state = self::MESSAGE_EQUALS_FALLBACK;

            $fallbackCatalogue = $catalogue->getFallBackCatalogue();
            while ($fallbackCatalogue) {
                if ($fallbackCatalogue->defines($id, $domain)) {
                    $locale = $fallbackCatalogue->getLocale();
                    break;
                }
            }
        } else {
            $state = self::MESSAGE_MISSING;
        }

        $this->messages[] = array(
            'locale' => $locale,
            'domain' => $domain,
            'id' => $id,
            'translation' => $translation,
            'state' => $state,
        );
    }
}
