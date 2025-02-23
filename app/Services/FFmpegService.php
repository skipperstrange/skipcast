<?php

namespace App\Services;

use FFMpeg;
use FFMpeg\FFMpeg as FFMpegInstance;
use FFMpeg\FFProbe;
use getID3;

class FFmpegService
{
    protected $ffmpeg;
    protected $ffprobe;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create();
        $this->ffprobe = FFProbe::create();
    }

    /**
     * Convert a media file to a different format.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $format
     * @return void
     */
    public function convert($inputFile, $outputFile, $format)
    {
        $this->ffmpeg->open($inputFile)
            ->save($format, $outputFile);
    }

    /**
     * Get metadata of a media file.
     *
     * @param string $file
     * @return array
     */
    public function getMetadata($file)
    {
        return $this->ffprobe
            ->format($file) // returns FFMpeg\Format
            ->all(); // returns all available format information
    }

    /**
     * Trim a media file.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param int $start
     * @param int $duration
     * @return void
     */
    public function trim($inputFile, $outputFile, $start, $duration)
    {
        $this->ffmpeg->open($inputFile)
            ->clip(FFMpeg\Coordinate\TimeCode::fromSeconds($start), $duration)
            ->save(new FFMpeg\Format\Audio\Mp3(), $outputFile);
    }

    /**
     * Add a watermark to a video.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $watermarkFile
     * @return void
     */
    public function addWatermark($inputFile, $outputFile, $watermarkFile)
    {
        $this->ffmpeg->open($inputFile)
            ->filters()->watermark($watermarkFile, [
                'position' => 'relative',
                'bottom' => 10,
                'right' => 10,
            ])
            ->save(new FFMpeg\Format\Video\X264(), $outputFile);
    }

    public function editMetadata($inputFile, $outputFile, array $metadata)
    {
        // Open the media file
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($inputFile);

        // Set the metadata
        $video->addMetadata($metadata);

        // Save the file with the new metadata
        $video->save(new FFMpeg\Format\Video\X264(), $outputFile);
    }

    public function getMediaType($file)
    {
        $mimeType = mime_content_type($file);

        if (strpos($mimeType, 'audio/') === 0) {
            return 'audio';
        } elseif (strpos($mimeType, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }

        return 'unknown';
    }

    public function getAudioMetadata($file)
    {
        $getID3 = new getID3;
        return $getID3->analyze($file);
    }
} 