<?php

namespace App\Enums;

trait HasLabel
{
    public function label(): string
    {
        return __(self::LABEL_PREFIX.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
