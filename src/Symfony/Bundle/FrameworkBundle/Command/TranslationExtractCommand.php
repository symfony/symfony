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
use Symfony\Component\Translation\Command\TranslationExtractCommand as BaseTranslationExtractCommand;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', TranslationExtractCommand::class, BaseTranslationExtractCommand::class);

/**
 * @deprecated since Symfony 8.2, use Symfony\Component\Translation\Command\TranslationExtractCommand instead
 */
#[AsCommand(name: 'translation:extract', description: 'Extract missing translations keys from code to translation files')]
class TranslationExtractCommand extends BaseTranslationExtractCommand
{
    public function __construct(
        TranslationWriterInterface $writer,
        TranslationReaderInterface $reader,
        ExtractorInterface $extractor,
        string $defaultLocale,
        ?string $defaultTransPath = null,
        ?string $defaultViewsPath = null,
        array $transPaths = [],
        array $codePaths = [],
        array $enabledLocales = [],
    ) {
        // the application is not known yet, so the kernel can only be handed over lazily
        parent::__construct(fn () => $this->getApplication()->getKernel(), $writer, $reader, $extractor, $defaultLocale, $defaultTransPath, $defaultViewsPath, $transPaths, $codePaths, $enabledLocales);
    }
}
