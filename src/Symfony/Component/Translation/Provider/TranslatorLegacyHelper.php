<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Translator;

/**
 * The Translator::initializeCatalogue() method that was responsible for loading
 * message catalogues may be overwritten by clients.
 *
 * During the deprecation phase, this class can be used to call the existing
 * initializeCatalogue() implementation from places where MessageCatalogueProviderInterface
 * is expected.
 *
 * Caution: Because the implementation of Translator::initializeCatalogue() only
 * accepted a primary locale and relied on the fallback locales internal to Translator,
 * the $fallbackLocales argument to provideCatalogue() cannot be used.
 *
 * So, using this class is only safe from within Translator itself (because the
 * fallback locales it would pass are the same as initializeCatalogue() assumes anyway).
 *
 * @internal
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class TranslatorLegacyHelper implements MessageCatalogueProviderInterface
{
    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function addLoader($format, LoaderInterface $loader)
    {
    }

    public function getLoaders()
    {
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
    }

    public function provideCatalogue($locale, $fallbackLocales = array())
    {
        return $this->translator->accessInitializeCatalogue($locale);
    }
}
