<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\ReferenceRepository;

if (method_exists(ReferenceRepository::class, 'getReferences')) {
    /** @internal */
    trait AddFixtureImplementation
    {
        public function addFixture(FixtureInterface $fixture)
        {
            $this->doAddFixture($fixture);
        }
    }
} else {
    /** @internal */
    trait AddFixtureImplementation
    {
        public function addFixture(FixtureInterface $fixture): void
        {
            $this->doAddFixture($fixture);
        }
    }
}
