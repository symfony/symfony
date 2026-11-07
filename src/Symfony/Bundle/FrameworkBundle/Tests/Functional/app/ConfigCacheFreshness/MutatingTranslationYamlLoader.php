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

use Symfony\Component\Translation\Loader\YamlFileLoader;

class MutatingTranslationYamlLoader extends YamlFileLoader
{
    /**
     * Saves the input after parsing its previous messages and before the production loader registers its resource.
     *
     * @return array{key: string}
     */
    protected function loadResource(string $resource): array
    {
        $messages = parent::loadResource($resource);
        Mutation::change($resource);

        return $messages;
    }
}
