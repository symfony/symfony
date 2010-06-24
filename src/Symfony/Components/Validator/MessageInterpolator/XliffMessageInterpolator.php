<?php

namespace Symfony\Components\Validator\MessageInterpolator;

class XliffMessageInterpolator implements MessageInterpolatorInterface
{
    protected $translations = array();

    /**
     * Constructs an interpolator from the given XLIFF file
     *
     * @param string|array $file  One or more paths to existing XLIFF files
     */
    public function __construct($file)
    {
        $files = (array)$file;

        foreach ($files as $file) {
            $xml = $this->parseFile($file);
            $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

            foreach ($xml->xpath('//xliff:trans-unit') as $translation) {
                $this->translations[(string)$translation->source] = (string)$translation->target;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function interpolate($text, array $parameters = array())
    {
        if (isset($this->translations[$text])) {
            $text = $this->translations[$text];
        }

        $sources = array();
        $targets = array();

        foreach ($parameters as $key => $value) {
            $sources[] = '%'.$key.'%';
            $targets[] = (string)$value;
        }

        return str_replace($sources, $targets, $text);
    }

    /**
     * Validates and parses the given file into a SimpleXMLElement
     *
     * @param  string $file
     * @return SimpleXMLElement
     */
    protected function parseFile($file)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($file, LIBXML_COMPACT)) {
            throw new \Exception(implode("\n", $this->getXmlErrors()));
        }
        if (!$dom->schemaValidate(__DIR__.'/schema/dic/xliff-core/xliff-core-1.2-strict.xsd')) {
            throw new \Exception(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);

        return simplexml_import_dom($dom);
    }

    /**
     * Returns the XML errors of the internal XML parser
     *
     * @return array  An array of errors
     */
    protected function getXmlErrors()
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ? $error->file : 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $errors;
    }
}