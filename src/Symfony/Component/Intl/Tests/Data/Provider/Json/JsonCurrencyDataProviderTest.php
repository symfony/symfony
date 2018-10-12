<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Provider\Json;

use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\JsonBundleReader;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Tests\Data\Provider\AbstractCurrencyDataProviderTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group intl-data
 * @group legacy
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
