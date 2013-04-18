<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Stub;

use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface;
use Symfony\Component\Intl\ResourceBundle\RegionBundle;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StubRegionBundle extends RegionBundle
{
    public function __construct(StructuredBundleReaderInterface $reader)
    {
        parent::__construct(realpath(__DIR__ . '/../../Resources/data/region'), $reader);
    }
}
