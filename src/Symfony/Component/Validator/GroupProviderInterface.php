<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * Defines the interface for a validation group provider.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface GroupProviderInterface
{
    /**
     * Returns which validation groups should be used for a certain state
     * of the object.
     *
     * @return string[]|string[][]|GroupSequence
     */
    public function getGroups(object $object): array|GroupSequence;
}
