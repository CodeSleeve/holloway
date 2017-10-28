<?php

namespace Holloway\Functions;

class Str
{
    /**
     * Chomp a string a into an array of substrings.
     *
     * INPUT: chomp('.', 'foo.bar.baz.qux')
     *
     * OUTPUT: [
     *     'foo.bar.baz',
     *     'foo.bar',
     *     'foo'
     * ]
     *
     * @param  string $string
     * @return array
     */
    public static function chomp(string $delimiter, string $string) : array
    {
        $chomps = array_map(function($piece) use ($string) {
            return substr($string, 0, strpos($string, ".$piece"));
        }, array_reverse(explode($delimiter, $string)));

        return array_filter($chomps);
    }
}
