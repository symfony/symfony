<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\TranslationsCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Translation\Dumper\CsvFileDumper;
use Symfony\Component\Translation\Dumper\IcuResFileDumper;
use Symfony\Component\Translation\Dumper\IniFileDumper;
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Dumper\MoFileDumper;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Dumper\QtFileDumper;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Dumper\YamlFileDumper;
use Symfony\Component\Translation\Extractor\ChainExtractor;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Extractor\PhpExtractor;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\IcuDatFileLoader;
use Symfony\Component\Translation\Loader\IcuResFileLoader;
use Symfony\Component\Translation\Loader\IniFileLoader;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\QtFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translator.default', Translator::class)
            ->args([
                abstract_arg('translation loaders locator'),
                service('translator.formatter'),
                param('kernel.default_locale'),
                abstract_arg('translation loaders ids'),
                [
                    'cache_dir' => param('kernel.cache_dir').'/translations',
                    'debug' => param('kernel.debug'),
                ],
                abstract_arg('enabled locales'),
            ])
            ->call('setConfigCacheFactory', [service('config_cache_factory')])
            ->tag('kernel.locale_aware')

        ->alias(TranslatorInterface::class, 'translator')

        ->set('translator.logging', LoggingTranslator::class)
            ->args([
                service('translator.logging.inner'),
                service('logger'),
            ])
            ->tag('monolog.logger', ['channel' => 'translation'])

        ->set('translator.formatter.default', MessageFormatter::class)
            ->args([service('identity_translator')])

        ->set('translation.loader.php', PhpFileLoader::class)
            ->tag('translation.loader', ['alias' => 'php'])

        ->set('translation.loader.yml', YamlFileLoader::class)
            ->tag('translation.loader', ['alias' => 'yaml', 'legacy-alias' => 'yml'])

        ->set('translation.loader.xliff', XliffFileLoader::class)
            ->tag('translation.loader', ['alias' => 'xlf', 'legacy-alias' => 'xliff'])

        ->set('translation.loader.po', PoFileLoader::class)
            ->tag('translation.loader', ['alias' => 'po'])

        ->set('translation.loader.mo', MoFileLoader::class)
            ->tag('translation.loader', ['alias' => 'mo'])

        ->set('translation.loader.qt', QtFileLoader::class)
            ->tag('translation.loader', ['alias' => 'ts'])

        ->set('translation.loader.csv', CsvFileLoader::class)
            ->tag('translation.loader', ['alias' => 'csv'])

        ->set('translation.loader.res', IcuResFileLoader::class)
            ->tag('translation.loader', ['alias' => 'res'])

        ->set('translation.loader.dat', IcuDatFileLoader::class)
            ->tag('translation.loader', ['alias' => 'dat'])

        ->set('translation.loader.ini', IniFileLoader::class)
            ->tag('translation.loader', ['alias' => 'ini'])

        ->set('translation.loader.json', JsonFileLoader::class)
            ->tag('translation.loader', ['alias' => 'json'])

        ->set('translation.dumper.php', PhpFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'php'])

        ->set('translation.dumper.xliff', XliffFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'xlf'])

        ->set('translation.dumper.xliff.xliff', XliffFileDumper::class)
            ->args(['xliff'])
            ->tag('translation.dumper', ['alias' => 'xliff'])

        ->set('translation.dumper.po', PoFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'po'])

        ->set('translation.dumper.mo', MoFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'mo'])

        ->set('translation.dumper.yml', YamlFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'yml'])

        ->set('translation.dumper.yaml', YamlFileDumper::class)
            ->args(['yaml'])
            ->tag('translation.dumper', ['alias' => 'yaml'])

        ->set('translation.dumper.qt', QtFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'ts'])

        ->set('translation.dumper.csv', CsvFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'csv'])

        ->set('translation.dumper.ini', IniFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'ini'])

        ->set('translation.dumper.json', JsonFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'json'])

        ->set('translation.dumper.res', IcuResFileDumper::class)
            ->tag('translation.dumper', ['alias' => 'res'])

        ->set('translation.extractor.php', PhpExtractor::class)
            ->tag('translation.extractor', ['alias' => 'php'])

        ->set('translation.reader', TranslationReader::class)
        ->alias(TranslationReaderInterface::class, 'translation.reader')

        ->set('translation.extractor', ChainExtractor::class)
        ->alias(ExtractorInterface::class, 'translation.extractor')

        ->set('translation.writer', TranslationWriter::class)
        ->alias(TranslationWriterInterface::class, 'translation.writer')

        ->set('translation.warmer', TranslationsCacheWarmer::class)
            ->args([service(ContainerInterface::class)])
            ->tag('container.service_subscriber', ['id' => 'translator'])
            ->tag('kernel.cache_warmer')

        ->set('translation.locale_switcher', LocaleSwitcher::class)
            ->args([
                param('kernel.default_locale'),
                tagged_iterator('kernel.locale_aware', exclude: 'translation.locale_switcher'),
                service('router.request_context')->ignoreOnInvalid(),
            ])
            ->tag('kernel.reset', ['method' => 'reset'])
            ->tag('kernel.locale_aware')
        ->alias(LocaleAwareInterface::class, 'translation.locale_switcher')
        ->alias(LocaleSwitcher::class, 'translation.locale_switcher')
    ;
};
