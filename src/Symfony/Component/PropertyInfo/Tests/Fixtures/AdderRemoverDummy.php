<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AdderRemoverDummy
{
    private $analyses;
    private $feet;

    public function addAnalyse(Dummy $analyse)
    {
    }

    public function removeFoot(Dummy $foot)
    {
    }
}
