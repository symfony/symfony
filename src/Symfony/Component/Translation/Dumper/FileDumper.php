<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\RuntimeException;

/**
 * FileDumper is an implementation of DumperInterface that dump a message catalogue to file(s).
 *
 * Options:
 * - path (mandatory): the directory where the files should be saved
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
abstract class FileDumper implements DumperInterface
{
    /**
     * A template for the relative paths to files.
     *
     * @var string
     */
    protected $relativePathTemplate = '%domain%.%locale%.%extension%';

    /**
     * Sets the template for the relative paths to files.
     *
     * @param string $relativePathTemplate A template for the relative paths to files
     */
    public function setRelativePathTemplate(string $relativePathTemplate): void
    {
        $this->relativePathTemplate = $relativePathTemplate;
    }

    /**
     * Sets backup flag.
     *
     * @param bool
     */
    public function setBackup(bool $backup): void
    {
        if (false !== $backup) {
            throw new \LogicException('The backup feature is no longer supported.');
        }

        // the method is only present to not break BC
        // to be deprecated in 4.1
    }

    /**
     * {@inheritdoc}
     */
    public function dump(MessageCatalogue $messages, $options = array()): void
    {
        if (!array_key_exists('path', $options)) {
            throw new InvalidArgumentException('The file dumper needs a path option.');
        }

        // save a file for each domain
        foreach ($messages->getDomains() as $domain) {
            $fullpath = $options['path'].'/'.$this->getRelativePath($domain, $messages->getLocale());
            if (!file_exists($fullpath)) {
                $directory = dirname($fullpath);
                if (!file_exists($directory) && !@mkdir($directory, 0777, true)) {
                    throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
                }
            }
            // save file
            file_put_contents($fullpath, $this->formatCatalogue($messages, $domain, $options));
        }
    }

    /**
     * Transforms a domain of a message catalogue to its string representation.
     *
     *
     * @return string representation
     */
    abstract public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = array()): string;

    /**
     * Gets the file extension of the dumper.
     *
     * @return string file extension
     */
    abstract protected function getExtension(): string;

    /**
     * Gets the relative file path using the template.
     */
    private function getRelativePath(string $domain, string $locale): string
    {
        return strtr($this->relativePathTemplate, array(
            '%domain%' => $domain,
            '%locale%' => $locale,
            '%extension%' => $this->getExtension(),
        ));
    }
}
