<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Event;

use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
abstract class AbstractTranslationEvent extends Event
{
    public function __construct(
        public readonly TranslatorBag $translatorBag,
    ) {
    }
}
