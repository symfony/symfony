<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Validation;

class LegacyCollectionValidatorArray2Dot4ApiTest extends CollectionValidatorArrayTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_4;
    }
}
