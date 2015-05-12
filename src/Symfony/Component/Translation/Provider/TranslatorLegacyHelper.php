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
 * Implements MessageCatalogueProviderInterface by dispatching back
 * to a Translator instance.
 *
 * This is needed for providing a BC upgrade path. As of writing, the actual
 * loading of MessageCatalogues takes place below the protected Translator::initializeCatalogue()
 * method.
 *
 * Translator now features an accessInitializeCatalogue() method to allow access to possible
 * subclass implementations.
 *
 * This class expects to decorate another MessageCatalogueProviderInterface instance. Configuration
 * will be forwarded to this inner instance, however the provideCatalogue() implementation will
 * dispatch to the Translator. It is expected that the Translator will actually call the
 * "inner" instance in turn.
 *
 * @internal
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class TranslatorLegacyHelper implements MessageCatalogueProviderInterface
{
    /**
     * @var MessageCatalogueProviderInterface
     */
    private $inner;

    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator, MessageCatalogueProviderInterface $inner)
    {
        $this->translator = $translator;
        $this->inner = $inner;
    }

    public function addLoader($format, LoaderInterface $loader)
    {
        $this->inner->addLoader($format, $loader);
    }

    public function getLoaders()
    {
        return $this->inner->getLoaders();
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
        $this->inner->addResource($format, $resource, $locale, $domain);
    }

    public function provideCatalogue($locale, $fallbackLocales = array())
    {
        return $this->translator->accessInitializeCatalogue($locale);
    }
}
