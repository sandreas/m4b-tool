<?php


namespace M4bTool\Executables;


abstract class AbstractFfmpegBasedExecutable extends AbstractExecutable
{
    const SILENCE_DEFAULT_DB = "-30dB";
    const DEFAULT_BITRATE = 64000;
    const DEFAULT_SAMPLING_RATE = 22050;
    const SAMPLING_RATE_TO_BITRATE_MAPPING = [
        8000 => 24000,
        11025 => 32000,
        12000 => 32000,
        16000 => 48000,
        22050 => 64000,
        32000 => 96000,
        44100 => 128000,
    ];

    const VBR_QUALITY_TO_SAMPLING_RATE_MAPPING = [
        0 => 8000,
        20 => 12000,
        40 => 16000,
        60 => 22050,
        80 => 44100
    ];


    protected function percentToValue($percent, $min, $max, $decimals = 0)
    {
        $value = round((($percent * ($max - $min)) / 100) + $min, $decimals);
        if ($value < $min) {
            $value = $min;
        } else if ($value > $max) {
            $value = $max;
        }

        return $value;
    }

    protected function appendTrimSilenceOptionsToCommand(&$command, FileConverterOptions $options)
    {
        // https://ffmpeg.org/ffmpeg-filters.html#silenceremove
        if ($options->trimSilenceStart || $options->trimSilenceEnd) {
            $command[] = "-af";
            $command[] = sprintf("silenceremove=start_periods=%s:start_threshold=%s:stop_periods=%s", (int)$options->trimSilenceStart, static::SILENCE_DEFAULT_DB, (int)$options->trimSilenceEnd);
        }

    }

    protected function setEncodingQualityIfUndefined(FileConverterOptions $options)
    {
        // all options are already set
        if ($options->bitRate && $options->sampleRate) {
            return $options;
        }

        $desiredSampleRate = static::DEFAULT_SAMPLING_RATE;


        if ($options->vbrQuality <= 0) {
            // only sample rate is set => bitrate has to be determined
            if ($options->sampleRate) {
                if (isset(static::SAMPLING_RATE_TO_BITRATE_MAPPING[$options->sampleRate])) {
                    $options->bitRate = $this->bitrateToString(static::SAMPLING_RATE_TO_BITRATE_MAPPING[$options->sampleRate]);
                } else if ($options->sampleRate < 8000) {
                    $options->bitRate = $this->bitrateToString(24000);
                } else {
                    $options->bitRate = $this->bitrateToString(128000);
                }
                return $options;
            }

            // neither bitrate nor sample rate is set, seek default for desired bitrate
            $desiredBitrate = $options->bitRate ? $this->bitrateToInt($options->bitRate) : static::DEFAULT_BITRATE;
            foreach (static::SAMPLING_RATE_TO_BITRATE_MAPPING as $sampleRate => $bitrate) {
                if ($bitrate <= $desiredBitrate) {
                    $desiredSampleRate = $sampleRate;
                } else {
                    break;
                }
            }

            $options->bitRate = $this->bitrateToString($desiredBitrate);
            $options->sampleRate = $desiredSampleRate;
            return $options;
        }

        // vbr mode, sample rate is already set
        if ($options->sampleRate) {
            return $options;
        }

        // determine according sample rate for vbrQuality value
        foreach (static::VBR_QUALITY_TO_SAMPLING_RATE_MAPPING as $vbrQualityValue => $sampleRate) {
            if ($vbrQualityValue <= $options->vbrQuality) {
                $desiredSampleRate = $sampleRate;
            } else {
                break;
            }
        }
        $options->sampleRate = $desiredSampleRate;
        return $options;
    }

    private function bitrateToInt($value)
    {
        if (stripos($value, "k") !== false) {
            return (int)(rtrim($value, "k") * 1000);
        }
        return (int)$value;
    }

    private function bitrateToString($integerValue)
    {
        return ceil($integerValue / 1000) . 'k';
    }
}
