<?php

namespace App\Services\Documents;

use InvalidArgumentException;

final readonly class Template
{
    public function __construct(
        public string $fontName,
        public int $fontSize,
        public float $lineSpacing,
        public float $marginLeftCm,
        public float $marginTopCm,
        public float $marginRightCm,
        public float $marginBottomCm,
        public float $firstLineIndentCm,
        public float $hangingIndentCm,
    ) {}

    /**
     * Load a preset from config/documents.php.
     */
    public static function fromConfig(string $key = 'default'): self
    {
        $preset = config("documents.templates.{$key}");

        if (! is_array($preset)) {
            throw new InvalidArgumentException("Unknown document template [{$key}].");
        }

        return new self(
            fontName: $preset['font_name'],
            fontSize: $preset['font_size'],
            lineSpacing: $preset['line_spacing'],
            marginLeftCm: $preset['margin_left_cm'],
            marginTopCm: $preset['margin_top_cm'],
            marginRightCm: $preset['margin_right_cm'],
            marginBottomCm: $preset['margin_bottom_cm'],
            firstLineIndentCm: $preset['first_line_indent_cm'],
            hangingIndentCm: $preset['hanging_indent_cm'],
        );
    }
}
