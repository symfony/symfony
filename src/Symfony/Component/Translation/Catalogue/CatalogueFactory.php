<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Catalogue;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
 */
class CatalogueFactory implements CatalogueFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create($locale, array $messages = array())
    {
        return new MessageCatalogue($locale, $messages);
    }
}
