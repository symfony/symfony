<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Translation\Command\TranslationDebugCommand as BaseTranslationDebugCommand;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', TranslationDebugCommand::class, BaseTranslationDebugCommand::class);

/**
 * @deprecated since Symfony 8.2, use Symfony\Component\Translation\Command\TranslationDebugCommand instead
 */
#[AsCommand(name: 'debug:translation', description: 'Display translation messages information')]
class TranslationDebugCommand extends BaseTranslationDebugCommand
{
    public function __construct(
        TranslatorInterface $translator,
        TranslationReaderInterface $reader,
        ExtractorInterface $extractor,
        ?string $defaultTransPath = null,
        ?string $defaultViewsPath = null,
        array $transPaths = [],
        array $codePaths = [],
        array $enabledLocales = [],
    ) {
        // the application is not known yet, so the kernel can only be handed over lazily
        parent::__construct(fn () => $this->getApplication()->getKernel(), $translator, $reader, $extractor, $defaultTransPath, $defaultViewsPath, $transPaths, $codePaths, $enabledLocales);
    }
}
