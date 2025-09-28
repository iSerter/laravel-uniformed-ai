<?php

namespace Iserter\UniformedAI\Support;

class Mapper
{
    public static function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    public static function renameKeys(array $data, array $map): array
    {
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
                unset($data[$from]);
            }
        }
        return $data;
    }
}
