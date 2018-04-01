<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\Data\Provider\Json;

use Symphony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symphony\Component\Intl\Data\Bundle\Reader\JsonBundleReader;
use Symphony\Component\Intl\Intl;
use Symphony\Component\Intl\Tests\Data\Provider\AbstractCurrencyDataProviderTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group intl-data
 */
class JsonCurrencyDataProviderTest extends AbstractCurrencyDataProviderTest
{
    protected function getDataDirectory()
    {
        return Intl::getDataDirectory();
    }

    /**
     * @return BundleReaderInterface
     */
    protected function createBundleReader()
    {
        return new JsonBundleReader();
    }
}
