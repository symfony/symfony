<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\FileStorage;

/**
 * FilesystemLoader is a loader that read templates from the filesystem.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FilesystemLoader extends Loader
{
    protected $templatePathPatterns;

    /**
     * Constructor.
     *
     * @param array $templatePathPatterns An array of path patterns to look for templates
     */
    public function __construct($templatePathPatterns)
    {
        if (!is_array($templatePathPatterns)) {
            $templatePathPatterns = array($templatePathPatterns);
        }

        $this->templatePathPatterns = $templatePathPatterns;
    }

    /**
     * Loads a template.
     *
     * @param array $template The template name as an array
     *
     * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
     */
    public function load($template)
    {
        if (self::isAbsolutePath($template['name']) && file_exists($template['name'])) {
            return new FileStorage($template['name']);
        }

        $replacements = array();
        foreach ($template as $key => $value) {
            $replacements['%'.$key.'%'] = $value;
        }

        $logs = array();
        foreach ($this->templatePathPatterns as $templatePathPattern) {
            if (is_file($file = strtr($templatePathPattern, $replacements))) {
                if (null !== $this->debugger) {
                    $this->debugger->log(sprintf('Loaded template file "%s"', $file));
                }

                return new FileStorage($file);
            }

            if (null !== $this->debugger) {
                $logs[] = sprintf('Failed loading template file "%s"', $file);
            }
        }

        if (null !== $this->debugger) {
            foreach ($logs as $log) {
                $this->debugger->log($log);
            }
        }

        return false;
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param array     $template The template name as an array
     * @param timestamp $time     The last modification time of the cached template
     */
    public function isFresh($template, $time)
    {
        if (false === $template = $this->load($template))
        {
            return false;
        }

        return filemtime((string) $template) < $time;
    }

    /**
     * Returns true if the file is an existing absolute path.
     *
     * @param string $file A path
     *
     * @return true if the path exists and is absolute, false otherwise
     */
    static protected function isAbsolutePath($file)
    {
        if ($file[0] == '/' || $file[0] == '\\' 
            || (strlen($file) > 3 && ctype_alpha($file[0]) 
                && $file[1] == ':' 
                && ($file[2] == '\\' || $file[2] == '/')
            )
        ) {
            return true;
        }

        return false;
    }
}
