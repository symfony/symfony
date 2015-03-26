<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Catalogue\CatalogueFactoryInterface;
use Symfony\Component\Translation\Catalogue\CatalogueFactory;

/**
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
 */
abstract class AbstractLoader implements LoaderInterface
{
    /**
     * @var CatalogueFactoryInterface
     */
    protected $catalogueFactory;

    /**
     * @param CatalogueFactoryInterface $catalogueFactory
     */
    public function __construct(CatalogueFactoryInterface $catalogueFactory = null)
    {
        $this->catalogueFactory = $catalogueFactory ?: new CatalogueFactory();
    }
}
