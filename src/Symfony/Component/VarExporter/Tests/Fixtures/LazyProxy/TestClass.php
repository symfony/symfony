<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy;

#[\AllowDynamicProperties]
class TestClass
{
    public function __construct(
        protected \stdClass $dep,
    ) {
    }

    public function getDep(): \stdClass
    {
        return $this->dep;
    }

    public function __destruct()
    {
        $this->dep->destructed = true;
    }
}
