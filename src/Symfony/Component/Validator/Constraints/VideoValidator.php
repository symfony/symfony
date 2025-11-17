<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Kev <https://github.com/symfonyaml>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VideoValidator extends FileValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Video) {
            throw new UnexpectedTypeException($constraint, Video::class);
        }

        $violations = \count($this->context->getViolations());

        parent::validate($value, $constraint);

        $failed = \count($this->context->getViolations()) !== $violations;

        if ($failed || null === $value || '' === $value) {
            return;
        }

        if (null === $constraint->minWidth && null === $constraint->maxWidth
            && null === $constraint->minHeight && null === $constraint->maxHeight
            && null === $constraint->minPixels && null === $constraint->maxPixels
            && null === $constraint->minRatio && null === $constraint->maxRatio
            && $constraint->allowSquare && $constraint->allowLandscape && $constraint->allowPortrait
        ) {
            return;
        }

        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-select_streams', 'v',
            '-show_entries', 'stream=index,codec_type,codec_name,width,height',
            '-show_entries', 'format=format_name',
            '-of', 'json',
            (string) $value,
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->context->buildViolation($constraint->corruptedMessage)
                ->setCode(Video::CORRUPTED_VIDEO_ERROR)
                ->addViolation();

            return;
        }

        $meta = json_decode($process->getOutput(), true) ?: [];
        $streams = $meta['streams'] ?? [];
        $formats = explode(',', strtolower($meta['format']['format_name'] ?? 'unknown'));

        if (!($streams[0]['width'] ?? false) || !($streams[0]['height'] ?? false)) {
            $this->context->buildViolation($constraint->sizeNotDetectedMessage)
                ->setCode(Video::SIZE_NOT_DETECTED_ERROR)
                ->addViolation();

            return;
        }

        $width = $streams[0]['width'];
        $height = $streams[0]['height'];

        if (1 !== \count($streams)) {
            $this->context->buildViolation($constraint->multipleVideoStreamsMessage)
                ->setCode(Video::MULTIPLE_VIDEO_STREAMS_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->allowedCodecs) {
            foreach ($streams as $stream) {
                $codec = strtolower($stream['codec_name'] ?? 'unknown');
                if (!\in_array($codec, $constraint->allowedCodecs, true)) {
                    $this->context->buildViolation($constraint->unsupportedCodecMessage)
                        ->setParameter('{{ codec }}', $codec)
                        ->setCode(Video::UNSUPPORTED_VIDEO_CODEC_ERROR)
                        ->addViolation();

                    return;
                }
            }
        }

        if ($constraint->allowedContainers && !array_intersect($formats, $constraint->allowedContainers)) {
            $this->context->buildViolation($constraint->unsupportedContainerMessage)
                ->setParameter('{{ container }}', $formats[0])
                ->setCode(Video::UNSUPPORTED_VIDEO_CONTAINER_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->minWidth) {
            if ($constraint->minWidth < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum width.', $constraint->minWidth));
            }

            if ($width < $constraint->minWidth) {
                $this->context->buildViolation($constraint->minWidthMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ min_width }}', $constraint->minWidth)
                    ->setCode(Video::TOO_NARROW_ERROR)
                    ->addViolation();

                return;
            }
        }

        if ($constraint->maxWidth) {
            if ($constraint->maxWidth < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum width.', $constraint->maxWidth));
            }

            if ($width > $constraint->maxWidth) {
                $this->context->buildViolation($constraint->maxWidthMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ max_width }}', $constraint->maxWidth)
                    ->setCode(Video::TOO_WIDE_ERROR)
                    ->addViolation();

                return;
            }
        }

        if ($constraint->minHeight) {
            if ($constraint->minHeight < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum height.', $constraint->minHeight));
            }

            if ($height < $constraint->minHeight) {
                $this->context->buildViolation($constraint->minHeightMessage)
                    ->setParameter('{{ height }}', $height)
                    ->setParameter('{{ min_height }}', $constraint->minHeight)
                    ->setCode(Video::TOO_LOW_ERROR)
                    ->addViolation();

                return;
            }
        }

        if ($constraint->maxHeight) {
            if ($constraint->maxHeight < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum height.', $constraint->maxHeight));
            }

            if ($height > $constraint->maxHeight) {
                $this->context->buildViolation($constraint->maxHeightMessage)
                    ->setParameter('{{ height }}', $height)
                    ->setParameter('{{ max_height }}', $constraint->maxHeight)
                    ->setCode(Video::TOO_HIGH_ERROR)
                    ->addViolation();
            }
        }

        $pixels = $width * $height;

        if (null !== $constraint->minPixels) {
            if ($constraint->minPixels < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum amount of pixels.', $constraint->minPixels));
            }

            if ($pixels < $constraint->minPixels) {
                $this->context->buildViolation($constraint->minPixelsMessage)
                    ->setParameter('{{ pixels }}', $pixels)
                    ->setParameter('{{ min_pixels }}', $constraint->minPixels)
                    ->setParameter('{{ height }}', $height)
                    ->setParameter('{{ width }}', $width)
                    ->setCode(Video::TOO_FEW_PIXEL_ERROR)
                    ->addViolation();
            }
        }

        if (null !== $constraint->maxPixels) {
            if ($constraint->maxPixels < 0) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum amount of pixels.', $constraint->maxPixels));
            }

            if ($pixels > $constraint->maxPixels) {
                $this->context->buildViolation($constraint->maxPixelsMessage)
                    ->setParameter('{{ pixels }}', $pixels)
                    ->setParameter('{{ max_pixels }}', $constraint->maxPixels)
                    ->setParameter('{{ height }}', $height)
                    ->setParameter('{{ width }}', $width)
                    ->setCode(Video::TOO_MANY_PIXEL_ERROR)
                    ->addViolation();
            }
        }

        $ratio = round($height > 0 ? $width / $height : 0, 2);

        if (null !== $constraint->minRatio) {
            if (!is_numeric((string) $constraint->minRatio)) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum ratio.', $constraint->minRatio));
            }

            if ($ratio < round($constraint->minRatio, 2)) {
                $this->context->buildViolation($constraint->minRatioMessage)
                    ->setParameter('{{ ratio }}', $ratio)
                    ->setParameter('{{ min_ratio }}', round($constraint->minRatio, 2))
                    ->setCode(Video::RATIO_TOO_SMALL_ERROR)
                    ->addViolation();
            }
        }

        if (null !== $constraint->maxRatio) {
            if (!is_numeric((string) $constraint->maxRatio)) {
                throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum ratio.', $constraint->maxRatio));
            }

            if ($ratio > round($constraint->maxRatio, 2)) {
                $this->context->buildViolation($constraint->maxRatioMessage)
                    ->setParameter('{{ ratio }}', $ratio)
                    ->setParameter('{{ max_ratio }}', round($constraint->maxRatio, 2))
                    ->setCode(Video::RATIO_TOO_BIG_ERROR)
                    ->addViolation();
            }
        }

        if (!$constraint->allowSquare && $width == $height) {
            $this->context->buildViolation($constraint->allowSquareMessage)
                ->setParameter('{{ width }}', $width)
                ->setParameter('{{ height }}', $height)
                ->setCode(Video::SQUARE_NOT_ALLOWED_ERROR)
                ->addViolation();
        }

        if (!$constraint->allowLandscape && $width > $height) {
            $this->context->buildViolation($constraint->allowLandscapeMessage)
                ->setParameter('{{ width }}', $width)
                ->setParameter('{{ height }}', $height)
                ->setCode(Video::LANDSCAPE_NOT_ALLOWED_ERROR)
                ->addViolation();
        }

        if (!$constraint->allowPortrait && $width < $height) {
            $this->context->buildViolation($constraint->allowPortraitMessage)
                ->setParameter('{{ width }}', $width)
                ->setParameter('{{ height }}', $height)
                ->setCode(Video::PORTRAIT_NOT_ALLOWED_ERROR)
                ->addViolation();
        }
    }
}
