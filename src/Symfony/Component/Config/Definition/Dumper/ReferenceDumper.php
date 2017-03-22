<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Dumper;

use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Dumps a reference configuration for the given configuration/node instance.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
abstract class ReferenceDumper
{
    protected $reference;
    protected $withDoc;

    public function __construct($withDoc = false)
    {
        $this->withDoc = $withDoc;
    }

    abstract public function dump(ConfigurationInterface $configuration, $namespace = null);
}
