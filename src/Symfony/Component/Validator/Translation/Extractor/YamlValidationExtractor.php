<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation\Extractor;

use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;

/**
 * Extractor of validation messages from yaml files.
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
class YamlValidationExtractor extends AbstractFileValidationExtractor
{
    /**
     * @inheritdoc
     */
    protected function createLoader(string $file)
    {
        return new YamlFileLoader($file);
    }
}
