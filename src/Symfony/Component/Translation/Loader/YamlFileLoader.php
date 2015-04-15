<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * YamlFileLoader loads translations from Yaml files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class YamlFileLoader extends FileLoader
{
    private $yamlParser;

    /**
     * {@inheritdoc}
     */
    protected function loadResource($resource)
    {
        if (null === $this->yamlParser) {
            $this->yamlParser = new YamlParser();
        }

        try {
            $messages = $this->yamlParser->parse(file_get_contents($resource));
        } catch (ParseException $e) {
            throw new InvalidResourceException(sprintf('Error parsing YAML, invalid file "%s"', $resource), 0, $e);
        }

        return $messages;
    }
}
