<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Fixtures;

use Symfony\Component\Templating\EngineInterface;

class TestEngine implements EngineInterface
{
    public function render($name, array $parameters = []): string
    {
    }

    public function exists($name): bool
    {
    }

    public function supports($name): bool
    {
        return true;
    }

    public function stream()
    {
    }
}
