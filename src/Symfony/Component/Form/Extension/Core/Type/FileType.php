<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FileUploadError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileType extends AbstractType
{
    public const KIB_BYTES = 1024;
    public const MIB_BYTES = 1048576;

    private const SUFFIXES = [
        1 => 'bytes',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    private $translator;

    public function __construct(TranslatorInterface $translator = null)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Ensure that submitted data is always an uploaded file or an array of some
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $requestHandler = $form->getConfig()->getRequestHandler();

            if ($options['multiple']) {
                $data = [];
                $files = $event->getData();

                if (!\is_array($files)) {
                    $files = [];
                }

                foreach ($files as $file) {
                    if ($requestHandler->isFileUpload($file)) {
                        $data[] = $file;

                        if (method_exists($requestHandler, 'getUploadFileError') && null !== $errorCode = $requestHandler->getUploadFileError($file)) {
                            $form->addError($this->getFileUploadError($errorCode));
                        }
                    }
                }

                // Since the array is never considered empty in the view data format
                // on submission, we need to evaluate the configured empty data here
                if ([] === $data) {
                    $emptyData = $form->getConfig()->getEmptyData();
                    $data = $emptyData instanceof \Closure ? $emptyData($form, $data) : $emptyData;
                }

                $event->setData($data);
            } elseif ($requestHandler->isFileUpload($event->getData()) && method_exists($requestHandler, 'getUploadFileError') && null !== $errorCode = $requestHandler->getUploadFileError($event->getData())) {
                $form->addError($this->getFileUploadError($errorCode));
            } elseif (!$requestHandler->isFileUpload($event->getData())) {
                $event->setData(null);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
            $view->vars['attr']['multiple'] = 'multiple';
        }

        $view->vars = array_replace($view->vars, [
            'type' => 'file',
            'value' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['multipart'] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $dataClass = null;
        if (class_exists(\Symfony\Component\HttpFoundation\File\File::class)) {
            $dataClass = function (Options $options) {
                return $options['multiple'] ? null : 'Symfony\Component\HttpFoundation\File\File';
            };
        }

        $emptyData = function (Options $options) {
            return $options['multiple'] ? [] : null;
        };

        $resolver->setDefaults([
            'compound' => false,
            'data_class' => $dataClass,
            'empty_data' => $emptyData,
            'multiple' => false,
            'allow_file_upload' => true,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a valid file.';
            },
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'file';
    }

    private function getFileUploadError(int $errorCode)
    {
        $messageParameters = [];

        if (\UPLOAD_ERR_INI_SIZE === $errorCode) {
            [$limitAsString, $suffix] = $this->factorizeSizes(0, self::getMaxFilesize());
            $messageTemplate = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
            $messageParameters = [
                '{{ limit }}' => $limitAsString,
                '{{ suffix }}' => $suffix,
            ];
        } elseif (\UPLOAD_ERR_FORM_SIZE === $errorCode) {
            $messageTemplate = 'The file is too large.';
        } else {
            $messageTemplate = 'The file could not be uploaded.';
        }

        if (null !== $this->translator) {
            $message = $this->translator->trans($messageTemplate, $messageParameters, 'validators');
        } else {
            $message = strtr($messageTemplate, $messageParameters);
        }

        return new FileUploadError($message, $messageTemplate, $messageParameters);
    }

    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * This method should be kept in sync with Symfony\Component\HttpFoundation\File\UploadedFile::getMaxFilesize().
     *
     * @return int|float The maximum size of an uploaded file in bytes (returns float if size > PHP_INT_MAX)
     */
    private static function getMaxFilesize()
    {
        $iniMax = strtolower(ini_get('upload_max_filesize'));

        if ('' === $iniMax) {
            return \PHP_INT_MAX;
        }

        $max = ltrim($iniMax, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($iniMax, -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

    /**
     * Converts the limit to the smallest possible number
     * (i.e. try "MB", then "kB", then "bytes").
     *
     * This method should be kept in sync with Symfony\Component\Validator\Constraints\FileValidator::factorizeSizes().
     *
     * @param int|float $limit
     */
    private function factorizeSizes(int $size, $limit)
    {
        $coef = self::MIB_BYTES;
        $coefFactor = self::KIB_BYTES;

        $limitAsString = (string) ($limit / $coef);

        // Restrict the limit to 2 decimals (without rounding! we
        // need the precise value)
        while (self::moreDecimalsThan($limitAsString, 2)) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
        }

        // Convert size to the same measure, but round to 2 decimals
        $sizeAsString = (string) round($size / $coef, 2);

        // If the size and limit produce the same string output
        // (due to rounding), reduce the coefficient
        while ($sizeAsString === $limitAsString) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
            $sizeAsString = (string) round($size / $coef, 2);
        }

        return [$limitAsString, self::SUFFIXES[$coef]];
    }

    /**
     * This method should be kept in sync with Symfony\Component\Validator\Constraints\FileValidator::moreDecimalsThan().
     */
    private static function moreDecimalsThan(string $double, int $numberOfDecimals): bool
    {
        return \strlen($double) > \strlen(round($double, $numberOfDecimals));
    }
}
