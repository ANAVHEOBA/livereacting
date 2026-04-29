<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use Illuminate\Support\Carbon;

class FfmpegCommandBuilderService
{
    public function build(LiveStream $liveStream, array $artifacts, array $outputs): array
    {
        [$width, $height] = $this->resolution($liveStream->format);
        $fps = (int) config('streaming.ffmpeg.fps', 30);
        $layers = $this->visibleLayers($liveStream->metadata['studio_snapshot'] ?? []);
        $outputCount = count($outputs);

        $command = [
            config('streaming.ffmpeg.bin'),
            '-hide_banner',
            '-loglevel',
            'warning',
            '-y',
            '-f',
            'lavfi',
            '-i',
            sprintf('color=c=0x111827:s=%dx%d:r=%d', $width, $height, $fps),
        ];

        $inputRegistry = [];
        $inputIndex = 1;

        foreach ($layers as $layer) {
            if (! in_array($layer['type'] ?? null, ['video', 'image', 'audio'], true)) {
                continue;
            }

            $source = $this->layerSource($layer);

            if (! $source) {
                continue;
            }

            $inputRegistry[(int) $layer['id']] = [
                'index' => $inputIndex,
                'type' => $layer['type'],
                'source' => $source,
            ];

            $command = array_merge($command, $this->inputArguments($layer, $source['path'], $fps));
            $inputIndex++;
        }

        $silenceInputIndex = $inputIndex;
        $command = array_merge($command, [
            '-f',
            'lavfi',
            '-i',
            'anullsrc=channel_layout=stereo:sample_rate=48000',
            '-filter_complex',
            $this->filterComplex(
                $layers,
                $inputRegistry,
                $silenceInputIndex,
                $width,
                $height,
                $artifacts,
                $outputCount
            ),
        ]);

        foreach (range(0, $outputCount - 1) as $index) {
            $command = array_merge($command, [
                '-map',
                $outputCount === 1 ? '[vout]' : "[vout{$index}]",
                '-map',
                '[aout]',
                '-c:v',
                'libx264',
                '-preset',
                config('streaming.ffmpeg.preset', 'veryfast'),
                '-tune',
                'zerolatency',
                '-pix_fmt',
                'yuv420p',
                '-r',
                (string) $fps,
                '-g',
                (string) config('streaming.ffmpeg.gop', 60),
                '-b:v',
                (string) config('streaming.ffmpeg.video_bitrate', '4500k'),
                '-maxrate',
                (string) config('streaming.ffmpeg.video_bitrate', '4500k'),
                '-bufsize',
                $this->bufferSize((string) config('streaming.ffmpeg.video_bitrate', '4500k')),
                '-c:a',
                'aac',
                '-b:a',
                (string) config('streaming.ffmpeg.audio_bitrate', '128k'),
                '-ar',
                '48000',
                '-ac',
                '2',
                '-f',
                'flv',
                $outputs[$index]['full_rtmp_url'],
            ]);
        }

        return $command;
    }

    public function visibleLayers(array $studioSnapshot): array
    {
        return collect($studioSnapshot['active_scene']['layers'] ?? [])
            ->filter(fn (array $layer): bool => (bool) ($layer['is_visible'] ?? false))
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    protected function filterComplex(
        array $layers,
        array $inputRegistry,
        int $silenceInputIndex,
        int $width,
        int $height,
        array $artifacts,
        int $outputCount
    ): string {
        $filters = ['[0:v]format=rgba,setsar=1[canvas0]'];
        $canvasLabel = 'canvas0';
        $canvasIndex = 0;
        $visualIndex = 0;
        $textIndex = 0;
        $audioLabels = [];

        foreach ($layers as $layer) {
            $type = $layer['type'] ?? null;
            $layerId = (int) ($layer['id'] ?? 0);

            if (in_array($type, ['video', 'image'], true) && isset($inputRegistry[$layerId])) {
                $visualLabel = $this->visualLayerLabel($visualIndex);
                $filters[] = $this->visualFilter($layer, $inputRegistry[$layerId]['index'], $visualLabel);

                $nextCanvas = 'canvas'.(++$canvasIndex);
                $filters[] = $this->overlayFilter($canvasLabel, $visualLabel, $layer, $nextCanvas);
                $canvasLabel = $nextCanvas;
                $visualIndex++;

                continue;
            }

            if (in_array($type, ['text', 'overlay'], true)) {
                $nextCanvas = 'canvas'.(++$canvasIndex);
                $filters = array_merge($filters, $this->textFilters(
                    $canvasLabel,
                    $layer,
                    $artifacts['layer_text_paths'] ?? [],
                    $nextCanvas,
                    $textIndex
                ));
                $canvasLabel = $nextCanvas;
                $textIndex++;

                continue;
            }

            if ($type === 'countdown') {
                $nextCanvas = 'canvas'.(++$canvasIndex);
                $filters = array_merge($filters, $this->countdownFilters(
                    $canvasLabel,
                    $layer,
                    $nextCanvas,
                    $textIndex
                ));
                $canvasLabel = $nextCanvas;
                $textIndex++;

                continue;
            }

            if ($type === 'audio' && isset($inputRegistry[$layerId])) {
                $audioLabel = "[a{$layerId}]";
                $filters[] = $this->audioFilter($layer, $inputRegistry[$layerId]['index'], $audioLabel);
                $audioLabels[] = $audioLabel;
            }
        }

        $filters[] = $this->summaryOverlayFilter($canvasLabel, $artifacts['overlay_path'], ++$canvasIndex);
        $canvasLabel = 'canvas'.$canvasIndex;

        if ($audioLabels === []) {
            $filters[] = "[{$silenceInputIndex}:a]volume=0[aout]";
        } elseif (count($audioLabels) === 1) {
            $filters[] = "{$audioLabels[0]}anull[aout]";
        } else {
            $inputs = implode('', $audioLabels);
            $filters[] = "{$inputs}amix=inputs=".count($audioLabels).':duration=longest:dropout_transition=0:normalize=0[aout]';
        }

        if ($outputCount <= 1) {
            $filters[] = "[{$canvasLabel}]format=yuv420p[vout]";

            return implode(';', $filters);
        }

        $splitOutputs = implode('', array_map(
            static fn (int $index): string => "[vout{$index}]",
            range(0, $outputCount - 1)
        ));

        $filters[] = "[{$canvasLabel}]format=yuv420p,split={$outputCount}{$splitOutputs}";

        return implode(';', $filters);
    }

    protected function inputArguments(array $layer, string $path, int $fps): array
    {
        $settings = $layer['settings'] ?? [];
        $loop = (bool) ($settings['loop'] ?? true);
        $type = $layer['type'] ?? null;

        if ($type === 'image') {
            return [
                '-loop',
                '1',
                '-framerate',
                (string) $fps,
                '-i',
                $path,
            ];
        }

        $arguments = ['-re'];

        if ($loop) {
            $arguments = array_merge($arguments, ['-stream_loop', '-1']);
        }

        $arguments[] = '-i';
        $arguments[] = $path;

        return $arguments;
    }

    protected function layerSource(array $layer): ?array
    {
        $asset = $layer['asset'] ?? null;

        if (! $asset) {
            return null;
        }

        $path = $asset['source_url'] ?? null;

        if (blank($path) && ! empty($asset['storage_path'])) {
            $localPath = storage_path('app/'.$asset['storage_path']);

            if (is_file($localPath)) {
                $path = $localPath;
            }
        }

        if (blank($path)) {
            return null;
        }

        return [
            'path' => $path,
            'mode' => $layer['type'] === 'image' ? 'image' : 'stream',
        ];
    }

    protected function visualFilter(array $layer, int $inputIndex, string $outputLabel): string
    {
        $position = $layer['position'] ?? [];
        $settings = $layer['settings'] ?? [];
        $width = max(1, (int) ($position['width'] ?? 1));
        $height = max(1, (int) ($position['height'] ?? 1));
        $fit = $settings['fit'] ?? 'cover';
        $opacity = max(0, min(1, (float) ($settings['opacity'] ?? 1)));

        $scale = match ($fit) {
            'contain' => "scale=w={$width}:h={$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2:color=black@0",
            'stretch' => "scale=w={$width}:h={$height}",
            default => "scale=w={$width}:h={$height}:force_original_aspect_ratio=increase,crop={$width}:{$height}",
        };

        $filters = [
            "[{$inputIndex}:v]setpts=PTS-STARTPTS",
            $scale,
            'setsar=1',
            'format=rgba',
        ];

        if ($opacity < 1) {
            $filters[] = 'colorchannelmixer=aa='.$this->formatNumber($opacity);
        }

        return implode(',', $filters).$outputLabel;
    }

    protected function overlayFilter(string $canvasLabel, string $visualLabel, array $layer, string $outputLabel): string
    {
        $position = $layer['position'] ?? [];
        $x = (int) ($position['x'] ?? 0);
        $y = (int) ($position['y'] ?? 0);

        return sprintf(
            '[%s]%soverlay=x=%d:y=%d:eof_action=pass:repeatlast=1[%s]',
            $canvasLabel,
            $visualLabel,
            $x,
            $y,
            $outputLabel
        );
    }

    protected function textFilters(
        string $canvasLabel,
        array $layer,
        array $layerTextPaths,
        string $outputLabel,
        int $textIndex
    ): array {
        $position = $layer['position'] ?? [];
        $settings = $layer['settings'] ?? [];
        $padding = max(0, (int) ($settings['padding'] ?? 0));
        $intermediate = "{$outputLabel}_bg";
        $filters = [];
        $current = $canvasLabel;

        if (filled($settings['background_color'] ?? null) && ($settings['background_opacity'] ?? 0) > 0) {
            $filters[] = sprintf(
                '[%s]drawbox=x=%d:y=%d:w=%d:h=%d:color=%s@%s:t=fill[%s]',
                $current,
                (int) ($position['x'] ?? 0),
                (int) ($position['y'] ?? 0),
                max(1, (int) ($position['width'] ?? 1)),
                max(1, (int) ($position['height'] ?? 1)),
                $this->normalizeColor((string) $settings['background_color']),
                $this->formatNumber((float) ($settings['background_opacity'] ?? 0)),
                $intermediate
            );
            $current = $intermediate;
        }

        $textPath = $layerTextPaths[(int) ($layer['id'] ?? 0)] ?? null;

        if (! $textPath) {
            return $filters;
        }

        $filters[] = sprintf(
            "[%s]drawtext=font='%s':textfile='%s':reload=1:fontcolor=%s@%s:fontsize=%d:line_spacing=%d:x=%s:y=%s[%s]",
            $current,
            $this->escapeFilterValue((string) ($settings['font_family'] ?? config('streaming.ffmpeg.font_family', 'Sans'))),
            $this->escapeFilterValue($textPath),
            $this->normalizeColor((string) ($settings['font_color'] ?? '#ffffff')),
            $this->formatNumber((float) ($settings['opacity'] ?? 1)),
            max(12, (int) ($settings['font_size'] ?? 42)),
            (int) ($settings['line_spacing'] ?? 8),
            $this->textXExpression($position, $settings, $padding),
            $this->textYExpression($position, $settings, $padding),
            $outputLabel
        );

        return $filters;
    }

    protected function countdownFilters(
        string $canvasLabel,
        array $layer,
        string $outputLabel,
        int $textIndex
    ): array {
        $position = $layer['position'] ?? [];
        $settings = $layer['settings'] ?? [];
        $padding = max(0, (int) ($settings['padding'] ?? 0));
        $intermediate = "{$outputLabel}_bg";
        $filters = [];
        $current = $canvasLabel;

        if (filled($settings['background_color'] ?? null) && ($settings['background_opacity'] ?? 0) > 0) {
            $filters[] = sprintf(
                '[%s]drawbox=x=%d:y=%d:w=%d:h=%d:color=%s@%s:t=fill[%s]',
                $current,
                (int) ($position['x'] ?? 0),
                (int) ($position['y'] ?? 0),
                max(1, (int) ($position['width'] ?? 1)),
                max(1, (int) ($position['height'] ?? 1)),
                $this->normalizeColor((string) $settings['background_color']),
                $this->formatNumber((float) ($settings['background_opacity'] ?? 0)),
                $intermediate
            );
            $current = $intermediate;
        }

        $remainingSeconds = 0;

        try {
            $remainingSeconds = max(0, now()->diffInSeconds(Carbon::parse((string) ($settings['ends_at'] ?? now())), false));
        } catch (\Throwable) {
            $remainingSeconds = 0;
        }

        $filters[] = sprintf(
            "[%s]drawtext=font='%s':text='%s':fontcolor=%s@%s:fontsize=%d:x=%s:y=%s[%s]",
            $current,
            $this->escapeFilterValue((string) ($settings['font_family'] ?? config('streaming.ffmpeg.font_family', 'Sans'))),
            $this->countdownTextExpression($remainingSeconds),
            $this->normalizeColor((string) ($settings['font_color'] ?? '#ffffff')),
            $this->formatNumber((float) ($settings['opacity'] ?? 1)),
            max(12, (int) ($settings['font_size'] ?? 52)),
            $this->textXExpression($position, $settings, $padding),
            $this->textYExpression($position, $settings, $padding),
            $outputLabel
        );

        return $filters;
    }

    protected function audioFilter(array $layer, int $inputIndex, string $outputLabel): string
    {
        $settings = $layer['settings'] ?? [];
        $volume = ($settings['muted'] ?? false)
            ? 0
            : max(0, min(4, (float) ($settings['volume'] ?? 1)));

        return sprintf(
            '[%d:a]aresample=48000,volume=%s%s',
            $inputIndex,
            $this->formatNumber($volume),
            $outputLabel
        );
    }

    protected function summaryOverlayFilter(string $canvasLabel, string $overlayPath, int $canvasIndex): string
    {
        return sprintf(
            "[%s]drawtext=font='%s':textfile='%s':reload=1:fontcolor=white@0.75:fontsize=%d:x=48:y=h-th-48[canvas%d]",
            $canvasLabel,
            $this->escapeFilterValue((string) config('streaming.ffmpeg.font_family', 'Sans')),
            $this->escapeFilterValue($overlayPath),
            24,
            $canvasIndex
        );
    }

    protected function textXExpression(array $position, array $settings, int $padding): string
    {
        $x = (int) ($position['x'] ?? 0);
        $width = max(1, (int) ($position['width'] ?? 1));

        return match ($settings['align'] ?? 'left') {
            'center' => sprintf('%d+(%d-text_w)/2', $x, $width),
            'right' => sprintf('%d+%d-text_w-%d', $x, $width, $padding),
            default => sprintf('%d+%d', $x, $padding),
        };
    }

    protected function textYExpression(array $position, array $settings, int $padding): string
    {
        $y = (int) ($position['y'] ?? 0);
        $height = max(1, (int) ($position['height'] ?? 1));

        return match ($settings['vertical_align'] ?? 'top') {
            'middle' => sprintf('%d+(%d-text_h)/2', $y, $height),
            'bottom' => sprintf('%d+%d-text_h-%d', $y, $height, $padding),
            default => sprintf('%d+%d', $y, $padding),
        };
    }

    protected function countdownTextExpression(int $remainingSeconds): string
    {
        $remaining = max(0, $remainingSeconds);

        return sprintf(
            '%%{eif\\:max(floor((%d-t)/3600)\\,0)\\:d\\:2}\\:%%{eif\\:max(mod(floor((%d-t)/60)\\,60)\\,0)\\:d\\:2}\\:%%{eif\\:max(mod(floor((%d-t))\\,60)\\,0)\\:d\\:2}',
            $remaining,
            $remaining,
            $remaining
        );
    }

    protected function visualLayerLabel(int $index): string
    {
        return "[layerv{$index}]";
    }

    protected function normalizeColor(string $color): string
    {
        return ltrim($color, '#');
    }

    protected function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    protected function resolution(string $format): array
    {
        return match ($format) {
            '1080p' => [1920, 1080],
            default => [1280, 720],
        };
    }

    protected function bufferSize(string $bitrate): string
    {
        if (str_ends_with($bitrate, 'k')) {
            return ((int) rtrim($bitrate, 'k') * 2).'k';
        }

        if (str_ends_with($bitrate, 'M')) {
            return ((int) rtrim($bitrate, 'M') * 2).'M';
        }

        return $bitrate;
    }

    protected function escapeFilterValue(string $value): string
    {
        return str_replace(
            ['\\', ':', "'"],
            ['\\\\', '\\:', "\\'"],
            $value
        );
    }
}
