<?php

namespace App\Reports;

/**
 * A single grid column of a report. The same definition drives the on-screen grid,
 * the Excel export and the PDF export.
 */
final readonly class Column
{
    private function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $total = false,
        public bool $groupable = false,
        public bool $visible = true,
        public ?string $urlKey = null,
        public ?string $colorKey = null,
        public ?int $width = null,
    ) {}

    public static function text(string $key, string $label, bool $groupable = false, bool $visible = true, ?int $width = null): self
    {
        return new self($key, $label, 'text', groupable: $groupable, visible: $visible, width: $width);
    }

    /** Text rendered as a link to the row's `$urlKey` value. */
    public static function link(string $key, string $label, string $urlKey): self
    {
        return new self($key, $label, 'link', urlKey: $urlKey);
    }

    /** Pill badge colored by the row's `$colorKey` value (hex color or CSS class). */
    public static function badge(string $key, string $label, string $colorKey, bool $groupable = true): self
    {
        return new self($key, $label, 'badge', groupable: $groupable, colorKey: $colorKey);
    }

    public static function int(string $key, string $label, bool $total = false): self
    {
        return new self($key, $label, 'int', total: $total);
    }

    public static function money(string $key, string $label, bool $total = true): self
    {
        return new self($key, $label, 'money', total: $total);
    }

    public static function percent(string $key, string $label): self
    {
        return new self($key, $label, 'percent');
    }

    public static function hours(string $key, string $label): self
    {
        return new self($key, $label, 'hours');
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, 'date');
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['int', 'money', 'percent', 'hours'], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'total' => $this->total,
            'groupable' => $this->groupable,
            'visible' => $this->visible,
            'urlKey' => $this->urlKey,
            'colorKey' => $this->colorKey,
            'width' => $this->width,
        ], fn (mixed $v) => $v !== null && $v !== false);
    }
}
