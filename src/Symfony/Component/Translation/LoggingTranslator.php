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

use Psr\Log\LoggerInterface;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class LoggingTranslator implements TranslatorInterface, TranslatorBagInterface, FallbackLocaleAwareInterface
{
    /**
     * @var TranslatorInterface|TranslatorBagInterface
     */
    private $translator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FallbackLocaleAwareInterface
     */
    private $fallbackLocaleAware;

    /**
     * @param TranslatorInterface $translator The translator must implement TranslatorBagInterface
     * @param LoggerInterface     $logger
     */
    public function __construct(TranslatorInterface $translator, LoggerInterface $logger)
    {
        if ($translator instanceof FallbackLocaleAwareInterface) {
            $this->fallbackLocaleAware = $translator;
        } else if ($translator instanceof TranslatorBagInterface) {
            @trigger_error(sprintf('The Translator "%" should implement \Symfony\Component\Translation\FallbackLocaleAwareInterface instead of (or in addtion to) \Symfony\Component\Translation\TranslatorBagInterface.', get_class($translator)), E_USER_DEPRECATED);
            $this->fallbackLocaleAware = new TranslatorBagToFallbackLocaleAwareAdapter($translator);
        } else {
            throw new \InvalidArgumentException(sprintf('The Translator "%s" implements neither \Symfony\Component\Translation\FallbackLocaleAwareInterface nor the deprecated \Symfony\Component\Translation\TranslatorBagInterface.', get_class($translator)));
        }

        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $trans = $this->translator->trans($id, $parameters, $domain, $locale);
        $this->log($id, $domain, $locale);

        return $trans;
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        $trans = $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
        $this->log($id, $domain, $locale);

        return $trans;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveLocale($id, $domain = null, $locale = null)
    {
        return $this->fallbackLocaleAware->resolveLocale($id, $domain, $locale);
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
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated TranslatorBagInterface implementation will be removed in 3.0.
     */
    public function getCatalogue($locale = null)
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            throw new \RuntimeException(sprintf('You called the deprecated \Symfony\Component\Translation\LoggingTranslator::getCatalogue() method, but the Translator provided to the \Symfony\Component\Translation\LoggingTranslator constructor does not implement TranslatorBagInterface. (Hint: The class is %s.)',  get_class($this->translator)));
        }

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
     * Logs for missing translations.
     *
     * @param string      $id
     * @param string|null $domain
     * @param string|null $locale
     */
    private function log($id, $domain, $locale)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        if (null === $locale) {
            $locale = $this->translator->getLocale();
        }

        $id = (string) $id;

        $resolvedLocale = $this->translator->resolveLocale($id, $domain, $locale);

        if ($locale === $resolvedLocale) {
            return;
        }

        if ($resolvedLocale === null) {
            $this->logger->warning('Translation not found.', array('id' => $id, 'domain' => $domain, 'locale' => $locale));
        } else {
            $this->logger->debug('Translation use fallback catalogue.', array('id' => $id, 'domain' => $domain, 'locale' => $resolvedLocale));
        }
    }
}
