<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Event;

/**
 * This event will be dispatched by the translation:push command just before the translations from the filesystem are
 * pushed to the provider.
 *
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class TranslationPushEvent extends AbstractTranslationEvent
{
}
