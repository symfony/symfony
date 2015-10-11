<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider;

use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * The MessageCatalogueProviderInterface provide a MessageCatalogue chain loaded.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
interface MessageCatalogueProviderInterface
{
    /**
     * Gets the message catalogue by locale.
     *
     * @param string $locale The locale
     *
     * @return MessageCatalogueInterface
     */
    public function getCatalogue($locale);
}
