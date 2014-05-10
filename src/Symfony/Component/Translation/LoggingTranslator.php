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
class LoggingTranslator implements TranslatorInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Translator      $translator
     * @param LoggerInterface $logger
     */
    public function __construct($translator, LoggerInterface $logger)
    {
        if (!($translator instanceof TranslatorInterface && $translator instanceof TranslatorBagInterface)) {
            throw new \InvalidArgumentException(sprintf('The Translator "%s" must implements TranslatorInterface and TranslatorBagInterface.', get_class($translator)));
        }

        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $trans = $this->translator->trans($id, $parameters , $domain , $locale);
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
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        $id = (string) $id;
        $catalogue = $this->translator->getCatalogue($locale);
        if ($catalogue->defines($id, $domain)) {
            return;
        }

        if ($catalogue->has($id, $domain)) {
            $this->logger->debug('Translation use fallback catalogue.', array('id' => $id, 'domain' => $domain, 'locale' => $catalogue->getLocale()));
        } else {
            $this->logger->warning('Translation not found.', array('id' => $id, 'domain' => $domain, 'locale' => $catalogue->getLocale()));
        }
    }
}
