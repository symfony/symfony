<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

trait ReflectionExtractableTrait
{
    public self $self;

    public function getSelf(): self
    {
        return $this;
    }

    public function setSelf(self $self): void
    {
        $this->self = $self;
    }
}
