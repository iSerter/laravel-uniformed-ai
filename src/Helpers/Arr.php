<?php

namespace Iserter\UniformedAI\Helpers;

class Arr
{
    public static function get(array $array, string $key, $default = null)
    {
        if ($key === '') return $array;
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) return $default;
            $array = $array[$segment];
        }
        return $array;
    }
}
