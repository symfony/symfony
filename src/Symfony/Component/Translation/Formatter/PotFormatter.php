<?php

namespace Symfony\Component\Translation\Formatter;

class PotFormatter implements FormatterInterface
{
    /**
     * {@inheritDoc}
     */
    public function format(array $messages)
    {
        $output[] = 'msgid ""';
        $output[] = 'msgstr ""';
        $output[] = '"Project-Id-Version: PACKAGE VERSION\n"';
        $output[] = '"POT-Creation-Date: YEAR-MO-DA HO:MI+ZONE\n"';
        $output[] = '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"';
        $output[] = '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"';
        $output[] = '"Language-Team: LANGUAGE <EMAIL@ADDRESS>\n"';
        $output[] = '"MIME-Version: 1.0\n"';
        $output[] = '"Content-Type: text/plain; charset=UTF-8\n"';
        $output[] = '"Content-Transfer-Encoding: 8bit\n"';
        $output[] = '"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n"';
        $output[] = '';

        foreach ($data as $source => $target) {
            $source = $this->clean($source);
            $target = $this->clean($target);

            $output[] = "msgid \"{$source}\"";
            $output[] = "msgstr \"{$target}\"";
            $output[] = '';
        }

        return implode("\n", $output) . "\n";
    }

    protected function clean($message)
    {
        $message = strtr($message, array("\\'" => "'", "\\\\" => "\\", "\r\n" => "\n"));
        $message = addcslashes($message, "\0..\37\\\"");
        return $message;
    }
}
