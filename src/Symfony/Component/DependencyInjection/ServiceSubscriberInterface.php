<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Contracts\Service\ServiceSubscriberInterface as BaseServiceSubscriberInterface;

/**
 * {@inheritdoc}
 *
 * @deprecated since Symfony 4.2, use Symfony\Contracts\Service\ServiceSubscriberInterface instead.
 */
interface ServiceSubscriberInterface extends BaseServiceSubscriberInterface
{
}
