<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

/**
 * AjaxData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class AjaxData implements ProfileDataInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(null);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
    }

    public function getName()
    {
        return 'ajax';
    }
}
