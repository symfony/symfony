<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Debug;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionFlattenerInterface;

/**
 * TwigFlattener adds twig files into FlattenException
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class TwigFlattener implements ExceptionFlattenerInterface
{
    private $loader;

    public function __construct(\Twig_LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function flatten(\Exception $exception, FlattenException $flattenException, $options = array())
    {
        $trace = $flattenException->getTrace();
        $origTrace = $exception->getTrace();

        switch (count($trace) - count($origTrace)) {
            case 0:
                $from = 0;
                break;
            case 1:
                $from = 1;
                break;
            default:
                throw new \InvalidArgumentException();
        }

        foreach ($origTrace as $key => $entry) {
            if (!isset($origTrace[$key - 1]) || !isset($entry['class']) || 'Twig_Template' === $entry['class'] || !is_subclass_of($entry['class'], 'Twig_Template')) {
                continue;
            }

            $template = unserialize(sprintf('O:%d:"%s":0:{}', strlen($entry['class']), $entry['class']));

            $data = array('name' => $template->getTemplateName());
            $path = $this->loader->getCacheKey($data['name']);
            if (is_file($path)) {
                $data['path'] = $path;
            }

            if (isset($origTrace[$key - 1]['line'])) {
                $line = $origTrace[$key - 1]['line'];
                foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
                    if ($codeLine <= $line) {
                        $data['line'] = $templateLine;
                        break;
                    }
                }
            }

            $trace[$from + $key - 1]['related_files'][] = $data;
            $trace[$from + $key - 1]['tags'][] = 'twig';
        }

        if ($from == 1 && $exception instanceof \Twig_Error) {
            $data = array(
                'path' => $exception->getTemplateFile(),
                'line' => $exception->getTemplateLine(),
            );

            $trace[0]['related_files'][] = $data;
            $trace[0]['tags'][] = 'twig';
        }

        $flattenException->setRawTrace($trace);

        return $flattenException;
    }
}
