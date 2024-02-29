<?php

if (!function_exists('safeTruncate')) {
    function safeTruncate($text, $nbChars = 90)
    {
        return mb_substr(strip_tags($text), 0, $nbChars);
    }
}