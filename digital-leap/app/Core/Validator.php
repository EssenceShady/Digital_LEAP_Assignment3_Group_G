<?php
declare(strict_types=1);

namespace Leap\Core;

final class Validator
{
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function date(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    public static function int($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]) !== false;
    }

    public static function decimal($value, float $min = 0.0, float $max = PHP_FLOAT_MAX): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $n = (float) $value;
        return $n >= $min && $n <= $max;
    }

    public static function length(string $value, int $min, int $max = PHP_INT_MAX): bool
    {
        $len = mb_strlen(trim($value));
        return $len >= $min && $len <= $max;
    }
}
