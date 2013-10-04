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
use Symfony\Component\Intl\ResourceBundle\CurrencyBundle;
use Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the currency bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CurrencyBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * @var CurrencyBundleInterface
     */
    private $currencyBundle;

    public function __construct(CurrencyBundleInterface $currencyBundle)
    {
        $this->currencyBundle = $currencyBundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'curr';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContext $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-currencies';

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir(array($tempDir, $tempDir.'/res'));

        // The currency data is contained in the locales and misc bundles
        // in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $context->getFilesystem()->mirror($context->getSourceDir().'/locales', $tempDir.'/txt');
            $context->getFilesystem()->copy($context->getSourceDir().'/misc/supplementalData.txt', $tempDir.'/txt/misc.txt');
        } else {
            $context->getFilesystem()->mirror($context->getSourceDir().'/curr', $tempDir.'/txt');
            $context->getFilesystem()->rename($tempDir.'/txt/supplementalData.txt', $tempDir.'/txt/misc.txt');
        }

        // Replace "supplementalData" in the file by "misc" before compilation
        file_put_contents($tempDir.'/txt/misc.txt', str_replace('supplementalData', 'misc', file_get_contents($tempDir.'/txt/misc.txt')));

        $context->getCompiler()->compile($tempDir.'/txt/misc.txt', $tempDir.'/res');

        // Read file, add locales and write again
        $reader = new BinaryBundleReader();
        $data = iterator_to_array($reader->read($tempDir.'/res', 'misc'));

        // Key must not exist
        assert(!isset($data['Locales']));

        $data['Locales'] = $context->getLocaleScanner()->scanLocales($tempDir.'/txt');

        $writer = new TextBundleWriter();
        $writer->write($tempDir.'/txt', 'misc', $data, false);

        // The temporary directory now contains all sources to be compiled
        return $tempDir.'/txt';
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContext $context)
    {
        // Remove the temporary directory
        $context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-currencies-source');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContext $context)
    {
        $currencies = array();

        foreach ($this->currencyBundle->getCurrencyNames('en') as $code => $name) {
            $currencies[$code] = array(
                CurrencyBundle::INDEX_NAME => $name,
                CurrencyBundle::INDEX_SYMBOL => $this->currencyBundle->getCurrencySymbol($code, 'en'),
                CurrencyBundle::INDEX_FRACTION_DIGITS => $this->currencyBundle->getFractionDigits($code),
                CurrencyBundle::INDEX_ROUNDING_INCREMENT => $this->currencyBundle->getRoundingIncrement($code),
            );
        }

        return array(
            'Currencies' => $currencies,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContext $context)
    {
    }
}
