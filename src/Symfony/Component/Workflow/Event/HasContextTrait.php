<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 *
 * @internal
 */
trait HasContextTrait
{
    private array $context = [];

    public function getContext(): array
    {
        return $this->context;
    }
}
