<?php

declare(strict_types=1);

/**
 * One-off generator for the attachment-extraction fixtures.
 * Run: php scripts/make-chat-fixtures.php
 */

$dir = dirname(__DIR__).'/tests/fixtures';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// ── DOCX ──────────────────────────────────────────────────────────────────
$docx = $dir.'/sample.docx';
@unlink($docx);

$zip = new ZipArchive;
$zip->open($docx, ZipArchive::CREATE);

$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    .'<Default Extension="xml" ContentType="application/xml"/>'
    .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    .'</Types>');

$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    .'</Relationships>');

$zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
    .'<w:p><w:r><w:t>Halo dunia</w:t></w:r></w:p>'
    .'<w:p><w:r><w:t>Baris kedua &amp; penutup.</w:t></w:r></w:p>'
    .'</w:body></w:document>');

$zip->close();

// ── PDF ───────────────────────────────────────────────────────────────────
$text = 'BT /F1 18 Tf 20 100 Td (Halo dunia dari PDF) Tj ET';
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream",
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
];

$pdf = "%PDF-1.4\n";
$offsets = [];

foreach ($objects as $i => $object) {
    $offsets[$i + 1] = strlen($pdf);
    $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
}

$xrefStart = strlen($pdf);
$pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

foreach ($offsets as $offset) {
    $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
}

$pdf .= 'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
$pdf .= 'startxref'."\n".$xrefStart."\n%%EOF\n";

file_put_contents($dir.'/sample.pdf', $pdf);

echo "Wrote {$docx} (".filesize($docx)." bytes)\n";
echo "Wrote {$dir}/sample.pdf (".filesize($dir.'/sample.pdf')." bytes)\n";
