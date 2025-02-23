<?php

namespace App\Services;

use getID3;
use Plutuss\Facades\MediaAnalyzer;
use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class MediaService
{
    protected string $ffmpegPath;
    protected string $ffprobePath;

    /**
     * Create a new MediaService instance.
     */
    public function __construct(
        protected getID3 $getId3
    ) {
        // Use specific paths from .env if set, otherwise use system PATH
        $this->ffmpegPath = env('FFMPEG_BINARIES') ?: 'ffmpeg';
        $this->ffprobePath = env('FFPROBE_BINARIES') ?: 'ffprobe';
    }

    /**
     * Execute an FFmpeg command
     */
    protected function runFFmpeg(array $arguments): string
    {
        $command = array_merge([$this->ffmpegPath], $arguments);
        $result = Process::run(implode(' ', $command));

        if (!$result->successful()) {
            throw new RuntimeException("FFmpeg command failed: " . $result->errorOutput());
        }

        return $result->output();
    }

    /**
     * Execute an FFprobe command
     */
    protected function runFFprobe(array $arguments): string
    {
        $command = array_merge([$this->ffprobePath], $arguments);
        $result = Process::run(implode(' ', $command));

        if (!$result->successful()) {
            throw new RuntimeException("FFprobe command failed: " . $result->errorOutput());
        }

        return $result->output();
    }

    /**
     * Get video metadata from a file.
     */
    public function getVideoMetadata(string $file): array
    {
        $output = $this->runFFprobe([
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $file
        ]);

        return json_decode($output, true);
    }

    /**
     * Get audio metadata from a file.
     */
    public function getAudioMetadata(string $file): array
    {
        return $this->getId3->analyze($file);
    }

    /**
     * Update video metadata.
     */
    public function updateVideoMetadata(string $inputFile, string $outputFile, array $metadata): void
    {
        $arguments = ['-i', $inputFile];

        // Add metadata arguments
        foreach ($metadata as $key => $value) {
            $arguments[] = '-metadata';
            $arguments[] = "{$key}={$value}";
        }

        // Add output format and file
        $arguments = array_merge($arguments, [
            '-c', 'copy',  // Copy without re-encoding
            $outputFile
        ]);

        $this->runFFmpeg($arguments);
    }

    /**
     * Update audio metadata.
     */
    public function updateAudioMetadata(string $inputFile, array $metadata): void
    {
        $extension = pathinfo($inputFile, PATHINFO_EXTENSION);
        $tempOutputFile = tempnam(sys_get_temp_dir(), 'audio_') . '.' . $extension;

        $arguments = ['-i', $inputFile];

        // Add metadata arguments
        foreach ($metadata as $key => $value) {
            $arguments[] = '-metadata';
            $arguments[] = "{$key}={$value}";
        }

        // Add output format and file
        $arguments = array_merge($arguments, [
            '-c', 'copy',  // Copy without re-encoding
            $tempOutputFile
        ]);

        $this->runFFmpeg($arguments);
        rename($tempOutputFile, $inputFile);
    }

    /**
     * Convert video media to a different format.
     */
    public function convertVideoMedia(string $inputFile, string $outputFile, string $format): void
    {
        $this->runFFmpeg([
            '-i', $inputFile,
            '-c:v', 'libx264',  // Use H.264 codec
            '-preset', 'medium', // Balance between speed and quality
            '-crf', '23',       // Constant Rate Factor (18-28 is good)
            $outputFile
        ]);
    }

    /**
     * Trim a video file.
     */
    public function trimVideoMedia(string $inputFile, string $outputFile, string $start, string $duration): void
    {
        $this->runFFmpeg([
            '-i', $inputFile,
            '-ss', $start,      // Start time
            '-t', $duration,    // Duration
            '-c', 'copy',       // Copy without re-encoding
            $outputFile
        ]);
    }

    /**
     * Add watermark to video.
     */
    public function addWatermarkToVideo(string $inputFile, string $outputFile, string $watermarkFile): void
    {
        $this->runFFmpeg([
            '-i', $inputFile,
            '-i', $watermarkFile,
            '-filter_complex', 'overlay=main_w-overlay_w-10:main_h-overlay_h-10',  // Bottom right with 10px padding
            $outputFile
        ]);
    }

    /**
     * Get the media type of a file.
     */
    public function getMediaType(string $file): string
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

    /**
     * Update metadata for a media file.
     */
    public function updateMetadata(Media $media, array $metadata): void
    {
        $filePath = storage_path("app/{$media->file_path}");

        if ($media->media_type === 'video') {
            $this->updateVideoMetadata($filePath, $filePath, array_merge([
                'comment' => 'Processed by SkipCast'
            ], $metadata));
        } elseif ($media->media_type === 'audio') {
            $this->updateAudioMetadata($filePath, array_merge([
                'comment' => 'Processed by SkipCast'
            ], $metadata));
        }
    }

    /**
     * Get all metadata from a media file.
     */
    public function getAllMetadata(Media $media): array
    {
        $filePath = storage_path("app/{$media->file_path}");
        
        if ($media->media_type === 'video') {
            $videoInfo = $this->getVideoMetadata($filePath);
            $streams = $videoInfo['streams'] ?? [];
            $videoStream = collect($streams)->firstWhere('codec_type', 'video');
            
            return [
                'resolution' => $videoStream['width'] . 'x' . $videoStream['height'],
                'codec' => $videoStream['codec_name'] ?? null,
                'duration' => $videoInfo['format']['duration'] ?? null,
                'bitrate' => $videoInfo['format']['bit_rate'] ?? null,
                'size' => $videoInfo['format']['size'] ?? null,
            ];
        } elseif ($media->media_type === 'audio') {
            $audioInfo = $this->getAudioMetadata($filePath);
            
            return [
                'title' => $audioInfo['tags']['id3v2']['title'][0] ?? null,
                'track' => $audioInfo['tags']['id3v2']['track_number'][0] ?? null,
                'album' => $audioInfo['tags']['id3v2']['album'][0] ?? null,
                'year' => $audioInfo['tags']['id3v2']['year'][0] ?? null,
                'artist' => $audioInfo['tags']['id3v2']['artist'][0] ?? null,
                'duration' => $audioInfo['playtime_string'] ?? null,
                'bitrate' => $audioInfo['bitrate'] ?? null,
                'size' => $audioInfo['filesize'] ?? null,
                'channelmode' => $audioInfo['audio']['channelmode'] ?? null,
            ];
        }
        
        return [];
    }
}