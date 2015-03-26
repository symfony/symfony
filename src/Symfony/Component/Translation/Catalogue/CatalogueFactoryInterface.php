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

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
interface CatalogueFactoryInterface
{
    /**
     * @param string $locale   The locale
     * @param array  $messages An array of messages classified by domain
     */
    public function create($locale, array $messages = array());
}
