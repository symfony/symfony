<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Component\Translation\Loader\IcuResFileLoader;

class MutatingTranslationIcuResLoader extends IcuResFileLoader
{
    /**
     * Replaces the compiled bundle after extracting its old messages and before resource registration.
     *
     * @param array<string, string> $messages
     *
     * @return array<string, string>
     */
    protected function flatten(\ResourceBundle $rb, array &$messages = [], ?string $path = null): array
    {
        $result = parent::flatten($rb, $messages, $path);
        if (null === $path) {
            Mutation::change(Mutation::$directory.'/inputs/res');
        }

        return $result;
    }
}
