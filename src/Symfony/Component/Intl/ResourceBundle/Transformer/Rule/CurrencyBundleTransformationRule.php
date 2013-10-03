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
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContextInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContextInterface;
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
    public function beforeCompile(CompilationContextInterface $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-currencies-source';

        // The currency data is contained in the locales and misc bundles
        // in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $supplementalData = $context->getSourceDir().'/misc/supplementalData.txt';
            $sourceDir = $context->getSourceDir().'/locales';
        } else {
            $supplementalData = $context->getSourceDir().'/curr/supplementalData.txt';
            $sourceDir = $context->getSourceDir().'/curr';
        }

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir(array(
            $tempDir,
            $tempDir.'/txt',
            $tempDir.'/res'
        ));
        $context->getFilesystem()->copy($supplementalData, $tempDir.'/txt/misc.txt');

        // Replace "supplementalData" in the file by "misc" before compilation
        file_put_contents($tempDir.'/txt/misc.txt', str_replace('supplementalData', 'misc', file_get_contents($tempDir.'/txt/misc.txt')));

        $context->getCompiler()->compile($tempDir.'/txt', $tempDir.'/res');

        // Read file, add locales and write again
        $reader = new BinaryBundleReader();
        $data = iterator_to_array($reader->read($tempDir.'/res', 'misc'));

        // Key must not exist
        assert(!isset($data['Locales']));

        $data['Locales'] = $context->getLocaleScanner()->scanLocales($sourceDir);

        $writer = new TextBundleWriter();
        $writer->write($sourceDir, 'misc', $data, false);

        return $sourceDir;
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContextInterface $context)
    {
        // Remove supplementalData.res, whose content is contained within misc.res
        $context->getFilesystem()->remove($context->getBinaryDir().'/curr/supplementalData.res');

        // Remove the temporary directory
        $context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-currencies-source');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContextInterface $context)
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
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }
}
