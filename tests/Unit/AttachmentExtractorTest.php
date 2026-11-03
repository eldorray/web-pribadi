<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AttachmentExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentExtractorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return __DIR__.'/../fixtures/'.$name;
    }

    #[Test]
    public function extracts_text_from_docx(): void
    {
        $text = AttachmentExtractor::extract(
            $this->fixture('sample.docx'),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->assertNotNull($text);
        $this->assertStringContainsString('Halo dunia', $text);
        $this->assertStringContainsString('Baris kedua & penutup.', $text);
    }

    #[Test]
    public function extracts_text_from_pdf(): void
    {
        $text = AttachmentExtractor::extract($this->fixture('sample.pdf'), 'application/pdf');

        $this->assertNotNull($text);
        $this->assertStringContainsString('Halo dunia', $text);
    }

    #[Test]
    public function returns_null_for_images(): void
    {
        $this->assertNull(AttachmentExtractor::extract($this->fixture('sample.docx'), 'image/png'));
    }

    #[Test]
    public function returns_null_for_random_bytes_as_docx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, random_bytes(512));

        try {
            $this->assertNull(AttachmentExtractor::extract($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function returns_null_for_random_bytes_as_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, random_bytes(512));

        try {
            $this->assertNull(AttachmentExtractor::extract($path, 'application/pdf'));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function returns_null_for_missing_file(): void
    {
        $this->assertNull(AttachmentExtractor::extract($this->fixture('tidak-ada.pdf'), 'application/pdf'));
    }
}
