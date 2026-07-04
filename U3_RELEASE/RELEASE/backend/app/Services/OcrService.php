<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wraps Tesseract OCR (installed in the backend Docker image).
 * Supports PDF (via poppler pdftoimage) and image files.
 *
 * Environment requirements:
 *   - tesseract installed with 'tha' and 'eng' language packs
 *   - poppler-utils for PDF → image conversion (pdftoppm)
 */
class OcrService
{
    private string $tesseractBin;
    private string $languages;

    public function __construct()
    {
        $this->tesseractBin = config('services.tesseract.binary', '/usr/bin/tesseract');
        $this->languages    = config('services.tesseract.languages', 'tha+eng');
    }

    /**
     * Run OCR on a local file path.
     *
     * @param  string $filePath  Local temp path
     * @param  string $mimeType  e.g. application/pdf or image/png
     * @return array{text: string, confidence: float, page_count: int}
     */
    public function extract(string $filePath, string $mimeType): array
    {
        if (str_contains($mimeType, 'pdf')) {
            return $this->extractFromPdf($filePath);
        }

        return $this->extractFromImage($filePath);
    }

    // ─── PDF Extraction ───────────────────────────────────────────

    private function extractFromPdf(string $pdfPath): array
    {
        $tempDir   = sys_get_temp_dir() . '/ocr_' . uniqid();
        $pagePrefix = $tempDir . '/page';

        if (!mkdir($tempDir, 0755, true)) {
            throw new RuntimeException("Cannot create temp dir: {$tempDir}");
        }

        try {
            // Convert PDF pages to PNG images at 300 DPI
            $cmd = sprintf(
                'pdftoppm -r 300 -png %s %s 2>&1',
                escapeshellarg($pdfPath),
                escapeshellarg($pagePrefix),
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('pdftoppm failed: ' . implode("\n", $output));
            }

            $pageFiles = glob($tempDir . '/page-*.png');
            natsort($pageFiles);
            $pageFiles = array_values($pageFiles);

            if (empty($pageFiles)) {
                throw new RuntimeException('No pages extracted from PDF');
            }

            $allText    = [];
            $confidences = [];

            foreach ($pageFiles as $pageFile) {
                $result       = $this->runTesseract($pageFile);
                $allText[]    = $result['text'];
                $confidences[] = $result['confidence'];
            }

            return [
                'text'       => implode("\n\n", $allText),
                'confidence' => count($confidences) > 0
                    ? array_sum($confidences) / count($confidences)
                    : 0.0,
                'page_count' => count($pageFiles),
            ];
        } finally {
            // Cleanup temp files
            array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
    }

    // ─── Image Extraction ─────────────────────────────────────────

    private function extractFromImage(string $imagePath): array
    {
        $result = $this->runTesseract($imagePath);

        return [
            'text'       => $result['text'],
            'confidence' => $result['confidence'],
            'page_count' => 1,
        ];
    }

    // ─── Core Tesseract Call ──────────────────────────────────────

    /**
     * @return array{text: string, confidence: float}
     */
    private function runTesseract(string $imagePath): array
    {
        $outputBase = sys_get_temp_dir() . '/tess_' . uniqid();
        $hocr       = false; // Use plain text output; switch to tsv for confidence

        // Plain text output
        $txtCmd = sprintf(
            '%s %s %s -l %s --oem 3 --psm 6 2>/dev/null',
            escapeshellcmd($this->tesseractBin),
            escapeshellarg($imagePath),
            escapeshellarg($outputBase . '_txt'),
            escapeshellarg($this->languages),
        );
        exec($txtCmd, $txtOut, $txtExit);

        $text = '';
        $txtFile = $outputBase . '_txt.txt';
        if (file_exists($txtFile)) {
            $text = file_get_contents($txtFile);
            unlink($txtFile);
        }

        // TSV output for confidence scores
        $tsvCmd = sprintf(
            '%s %s %s -l %s --oem 3 --psm 6 tsv 2>/dev/null',
            escapeshellcmd($this->tesseractBin),
            escapeshellarg($imagePath),
            escapeshellarg($outputBase . '_tsv'),
            escapeshellarg($this->languages),
        );
        exec($tsvCmd, $tsvOut, $tsvExit);

        $confidence = 0.0;
        $tsvFile    = $outputBase . '_tsv.tsv';
        if (file_exists($tsvFile)) {
            $confidence = $this->parseTsvConfidence($tsvFile);
            unlink($tsvFile);
        }

        if ($txtExit !== 0) {
            Log::warning('Tesseract OCR error', [
                'image' => $imagePath,
                'exit'  => $txtExit,
            ]);
        }

        return [
            'text'       => $text,
            'confidence' => $confidence,
        ];
    }

    private function parseTsvConfidence(string $tsvPath): float
    {
        $lines      = file($tsvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $sum        = 0.0;
        $count      = 0;

        foreach (array_slice($lines, 1) as $line) { // skip header
            $cols = explode("\t", $line);
            if (isset($cols[10]) && $cols[10] !== '' && is_numeric($cols[10])) {
                $conf = (float) $cols[10];
                if ($conf >= 0) {
                    $sum   += $conf;
                    $count++;
                }
            }
        }

        return $count > 0 ? ($sum / $count) / 100.0 : 0.0; // normalize to 0-1
    }
}
