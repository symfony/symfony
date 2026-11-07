<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * An action that can be run when an element is selected.
 *
 * All actions except Action.ShowCard can be used this way.
 */
interface SelectActionInterface extends ActionInterface
{
}
