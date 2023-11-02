<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

/**
 * @internal use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait instead
 */
trait ExpectDeprecationTraitForV10_2
{
    public function expectDeprecation(string $message): void
    {
        // Expected deprecations set by isolated tests need to be written to a file
        // so that the test running process can take account of them.
        if ($file = getenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE')) {
            $this->getTestResultObject()->beStrictAboutTestsThatDoNotTestAnything(false);
            $expectedDeprecations = file_get_contents($file);
            if ($expectedDeprecations) {
                $expectedDeprecations = array_merge(unserialize($expectedDeprecations), [$message]);
            } else {
                $expectedDeprecations = [$message];
            }
            file_put_contents($file, serialize($expectedDeprecations));

            return;
        }

        SymfonyTestEventsCollectorForV10_2::instance()->addExpectedDeprecation($message);
    }
}
