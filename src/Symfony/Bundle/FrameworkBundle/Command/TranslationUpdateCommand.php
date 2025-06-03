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

use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

class TranslationUpdateCommand extends TranslationExtractCommand
{
    public function __construct(
        private TranslationWriterInterface $writer,
        private TranslationReaderInterface $reader,
        private ExtractorInterface $extractor,
        private string $defaultLocale,
        private ?string $defaultTransPath = null,
        private ?string $defaultViewsPath = null,
        private array $transPaths = [],
        private array $codePaths = [],
        private array $enabledLocales = [],
    ) {
        trigger_deprecation('symfony/framework-bundle', '7.3', 'The "%s" class is deprecated, use "%s" instead.', __CLASS__, TranslationExtractCommand::class);
        parent::__construct($writer, $reader, $extractor, $defaultLocale, $defaultTransPath, $defaultViewsPath, $transPaths, $codePaths, $enabledLocales);
    }
}
