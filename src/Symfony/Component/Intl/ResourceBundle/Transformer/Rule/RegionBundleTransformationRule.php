<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer\Rule;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\ResourceBundle\RegionBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the region bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegionBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * @var RegionBundleInterface
     */
    private $regionBundle;

    public function __construct(RegionBundleInterface $regionBundle)
    {
        $this->regionBundle = $regionBundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'region';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContext $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-regions';

        // The region data is contained in the locales bundle in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $sourceDir = $context->getSourceDir() . '/locales';
        } else {
            $sourceDir = $context->getSourceDir() . '/region';
        }

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir(array($tempDir, $tempDir.'/res'));
        $context->getFilesystem()->mirror($sourceDir, $tempDir.'/txt');

        $context->getCompiler()->compile($tempDir.'/txt', $tempDir.'/res');

        $meta = array(
            'AvailableLocales' => $context->getLocaleScanner()->scanLocales($tempDir.'/txt'),
            'Countries' => array(),
        );

        $reader = new BinaryBundleReader();

        // Collect complete list of countries in all locales
        foreach ($meta['AvailableLocales'] as $locale) {
            $bundle = $reader->read($tempDir.'/res', $locale);

            // isset() on \ResourceBundle returns true even if the value is null
            if (isset($bundle['Countries']) && null !== $bundle['Countries']) {
                $meta['Countries'] = array_merge(
                    $meta['Countries'],
                    array_keys(iterator_to_array($bundle['Countries']))
                );
            }
        }

        $meta['Countries'] = array_unique($meta['Countries']);
        $meta['Countries'] = array_filter($meta['Countries'], function ($country) {
            return !ctype_digit((string) $country);
        });
        sort($meta['Countries']);

        // Create meta file with all available locales
        $writer = new TextBundleWriter();
        $writer->write($tempDir.'/txt', 'meta', $meta, false);

        return $tempDir.'/txt';
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContext $context)
    {
        // Remove the temporary directory
        $context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-regions');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContext $context)
    {
        return array(
            'Countries' => $this->regionBundle->getCountryNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContext $context)
    {
    }
}
