<?php

namespace Holloway\Functions;

class Arr
{
    /**
     * @param  array $value
     * @return array
     */
    public static function sort(array $value) : array
    {
        asort($value);

        return $value;
    }
}