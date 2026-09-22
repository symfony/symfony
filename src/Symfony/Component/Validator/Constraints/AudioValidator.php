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

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Valmont PEHAUT-PIETRI <https://github.com/Valmonzo>
 */
class AudioValidator extends FileValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Audio) {
            throw new UnexpectedTypeException($constraint, Audio::class);
        }

        $violations = \count($this->context->getViolations());

        parent::validate($value, $constraint);

        $failed = \count($this->context->getViolations()) !== $violations;

        if ($failed || null === $value || '' === $value) {
            return;
        }

        if (null === $constraint->minDuration && null === $constraint->maxDuration
            && null === $constraint->minBitrate && null === $constraint->maxBitrate
            && null === $constraint->minChannels && null === $constraint->maxChannels
            && !$constraint->allowedSampleRates
            && !$constraint->allowedCodecs
            && !$constraint->allowedContainers
        ) {
            return;
        }

        if (null !== $constraint->minDuration && $constraint->minDuration < 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum duration.', $constraint->minDuration));
        }

        if (null !== $constraint->maxDuration && $constraint->maxDuration <= 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum duration.', $constraint->maxDuration));
        }

        if (null !== $constraint->minBitrate && $constraint->minBitrate < 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum bitrate.', $constraint->minBitrate));
        }

        if (null !== $constraint->maxBitrate && $constraint->maxBitrate <= 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum bitrate.', $constraint->maxBitrate));
        }

        if (null !== $constraint->minChannels && $constraint->minChannels < 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid minimum amount of channels.', $constraint->minChannels));
        }

        if (null !== $constraint->maxChannels && $constraint->maxChannels <= 0) {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum amount of channels.', $constraint->maxChannels));
        }

        static $ffprobe;
        if (!$ffprobe) {
            if (!class_exists(Process::class)) {
                throw new LogicException('The Process component is required to use the Audio constraint. Try running "composer require symfony/process".');
            }
            if (!$ffprobe ??= (new ExecutableFinder())->find('ffprobe')) {
                throw new LogicException('The ffprobe binary is required to use the Audio constraint.');
            }
        }

        $process = new Process([
            $ffprobe,
            '-v', 'error',
            '-select_streams', 'a',
            '-show_entries', 'stream=index,codec_name,sample_rate,channels,bit_rate,duration',
            '-show_entries', 'format=format_name,duration,bit_rate',
            '-of', 'json',
            (string) $value,
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->context->buildViolation($constraint->corruptedMessage)
                ->setCode(Audio::CORRUPTED_AUDIO_ERROR)
                ->addViolation();

            return;
        }

        $meta = json_decode($process->getOutput(), true) ?: [];
        $streams = $meta['streams'] ?? [];

        if (!$streams) {
            $this->context->buildViolation($constraint->noAudioStreamMessage)
                ->setCode(Audio::NO_AUDIO_STREAM_ERROR)
                ->addViolation();

            return;
        }

        if (1 !== \count($streams)) {
            $this->context->buildViolation($constraint->multipleAudioStreamsMessage)
                ->setCode(Audio::MULTIPLE_AUDIO_STREAMS_ERROR)
                ->addViolation();

            return;
        }

        $stream = $streams[0];
        $format = $meta['format'] ?? [];

        if ($constraint->allowedCodecs) {
            $codec = strtolower($stream['codec_name'] ?? 'unknown');

            if (!\in_array($codec, array_map('strtolower', $constraint->allowedCodecs), true)) {
                $this->context->buildViolation($constraint->unsupportedCodecMessage)
                    ->setParameter('{{ codec }}', $codec)
                    ->setCode(Audio::UNSUPPORTED_AUDIO_CODEC_ERROR)
                    ->addViolation();

                return;
            }
        }

        if ($constraint->allowedContainers) {
            $containers = explode(',', strtolower($format['format_name'] ?? 'unknown'));

            if (!array_intersect($containers, array_map('strtolower', $constraint->allowedContainers))) {
                $this->context->buildViolation($constraint->unsupportedContainerMessage)
                    ->setParameter('{{ container }}', $containers[0])
                    ->setCode(Audio::UNSUPPORTED_AUDIO_CONTAINER_ERROR)
                    ->addViolation();

                return;
            }
        }

        // The checks below accumulate: a file may violate several bounds at once.
        if (null !== $constraint->minDuration || null !== $constraint->maxDuration) {
            $duration = $format['duration'] ?? null;

            if (!is_numeric($duration)) {
                $duration = $stream['duration'] ?? null;
            }

            if (!is_numeric($duration)) {
                $this->context->buildViolation($constraint->durationNotDetectedMessage)
                    ->setCode(Audio::DURATION_NOT_DETECTED_ERROR)
                    ->addViolation();
            } else {
                $duration = round((float) $duration, 2);

                if (null !== $constraint->minDuration && $duration < $constraint->minDuration) {
                    $this->context->buildViolation($constraint->minDurationMessage)
                        ->setParameter('{{ duration }}', $duration)
                        ->setParameter('{{ min_duration }}', $constraint->minDuration)
                        ->setCode(Audio::TOO_SHORT_ERROR)
                        ->addViolation();
                }

                if (null !== $constraint->maxDuration && $duration > $constraint->maxDuration) {
                    $this->context->buildViolation($constraint->maxDurationMessage)
                        ->setParameter('{{ duration }}', $duration)
                        ->setParameter('{{ max_duration }}', $constraint->maxDuration)
                        ->setCode(Audio::TOO_LONG_ERROR)
                        ->addViolation();
                }
            }
        }

        if (null !== $constraint->minBitrate || null !== $constraint->maxBitrate) {
            $bitrate = $stream['bit_rate'] ?? null;

            if (!is_numeric($bitrate)) {
                $bitrate = $format['bit_rate'] ?? null;
            }

            if (!is_numeric($bitrate)) {
                $this->context->buildViolation($constraint->bitrateNotDetectedMessage)
                    ->setCode(Audio::BITRATE_NOT_DETECTED_ERROR)
                    ->addViolation();
            } else {
                $bitrate = (int) $bitrate;

                if (null !== $constraint->minBitrate && $bitrate < $constraint->minBitrate) {
                    $this->context->buildViolation($constraint->minBitrateMessage)
                        ->setParameter('{{ bitrate }}', $bitrate)
                        ->setParameter('{{ min_bitrate }}', $constraint->minBitrate)
                        ->setCode(Audio::BITRATE_TOO_LOW_ERROR)
                        ->addViolation();
                }

                if (null !== $constraint->maxBitrate && $bitrate > $constraint->maxBitrate) {
                    $this->context->buildViolation($constraint->maxBitrateMessage)
                        ->setParameter('{{ bitrate }}', $bitrate)
                        ->setParameter('{{ max_bitrate }}', $constraint->maxBitrate)
                        ->setCode(Audio::BITRATE_TOO_HIGH_ERROR)
                        ->addViolation();
                }
            }
        }

        if ($constraint->allowedSampleRates) {
            $sampleRate = $stream['sample_rate'] ?? null;

            if (!is_numeric($sampleRate)) {
                $this->context->buildViolation($constraint->sampleRateNotDetectedMessage)
                    ->setCode(Audio::SAMPLE_RATE_NOT_DETECTED_ERROR)
                    ->addViolation();
            } else {
                $sampleRate = (int) $sampleRate;

                if (!\in_array($sampleRate, $constraint->allowedSampleRates, true)) {
                    $this->context->buildViolation($constraint->unsupportedSampleRateMessage)
                        ->setParameter('{{ sample_rate }}', $sampleRate)
                        ->setCode(Audio::UNSUPPORTED_SAMPLE_RATE_ERROR)
                        ->addViolation();
                }
            }
        }

        if (null !== $constraint->minChannels || null !== $constraint->maxChannels) {
            $channels = $stream['channels'] ?? null;

            if (!is_numeric($channels)) {
                $this->context->buildViolation($constraint->channelsNotDetectedMessage)
                    ->setCode(Audio::CHANNELS_NOT_DETECTED_ERROR)
                    ->addViolation();
            } else {
                $channels = (int) $channels;

                if (null !== $constraint->minChannels && $channels < $constraint->minChannels) {
                    $this->context->buildViolation($constraint->minChannelsMessage)
                        ->setParameter('{{ channels }}', $channels)
                        ->setParameter('{{ min_channels }}', $constraint->minChannels)
                        ->setCode(Audio::TOO_FEW_CHANNELS_ERROR)
                        ->addViolation();
                }

                if (null !== $constraint->maxChannels && $channels > $constraint->maxChannels) {
                    $this->context->buildViolation($constraint->maxChannelsMessage)
                        ->setParameter('{{ channels }}', $channels)
                        ->setParameter('{{ max_channels }}', $constraint->maxChannels)
                        ->setCode(Audio::TOO_MANY_CHANNELS_ERROR)
                        ->addViolation();
                }
            }
        }
    }
}
