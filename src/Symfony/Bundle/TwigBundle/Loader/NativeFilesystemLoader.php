<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Loader;

use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

/**
 * @author Behnoush Norouzali <behnoush.norouzi@gmail.com>
 *
 * @internal
 */
class NativeFilesystemLoader extends FilesystemLoader
{
    /**
     * {@inheritdoc}
     */
    protected function findTemplate($template, $throw = true)
    {
        try {
            return parent::findTemplate($template, $throw);
        } catch (LoaderError $e) {
            if ('' === $template || '@' === $template[0] || !preg_match('/^(?P<bundle>[^:]*?)(?:Bundle)?:(?P<path>[^:]*+):(?P<template>.+\.[^\.]+\.[^\.]+)$/', $template, $m)) {
                throw $e;
            }
            if ('' !== $m['path']) {
                $m['template'] = $m['path'].'/'.$m['template'];
            }
            if ('' !== $m['bundle']) {
                $suggestion = '@'.$m['bundle'].'/'.$m['template'];
            } else {
                $suggestion = $m['template'];
            }
            if (false === parent::findTemplate($suggestion, false)) {
                throw $e;
            }

            throw new LoaderError(sprintf('Template reference "%s" not found, did you mean "%s"?', $template, $suggestion), -1, null, $e);
        }
    }
}
