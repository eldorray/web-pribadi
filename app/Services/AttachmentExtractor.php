<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * Pull plain text out of an attachment so it can be pasted into the prompt.
 *
 * Images have no text and return null; so does anything unreadable — a corrupt
 * PDF, a renamed .zip, a scanned PDF with no text layer. Callers turn null into
 * a readable message instead of an exception.
 */
final class AttachmentExtractor
{
    /** Hard cap so one huge document cannot blow the prompt budget. */
    private const MAX_CHARS = 200000;

    public static function extract(string $absolutePath, string $mime = ''): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        try {
            $text = match (true) {
                $mime === 'application/pdf' => self::fromPdf($absolutePath),
                in_array($mime, [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/msword',
                ], true) => self::fromDocx($absolutePath),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, self::MAX_CHARS);
    }

    private static function fromPdf(string $path): ?string
    {
        return (new Parser)->parseFile($path)->getText();
    }

    private static function fromDocx(string $path): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        // Paragraph and line breaks become newlines before the tags go away.
        $text = str_replace(['</w:p>', '<w:br/>', '<w:br />', '</w:tr>'], "\n", $xml);
        $text = strip_tags($text);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
