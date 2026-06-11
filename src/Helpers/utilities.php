<?php

if (!function_exists('safeTruncate')) {
    function safeTruncate($text, $nbChars = 90)
    {
        return mb_substr(strip_tags($text ?? ''), 0, $nbChars);
    }
}

if (!function_exists('discussionsAvatarUrl')) {
    function discussionsAvatarUrl($user, $size = null)
    {
        return $user->avatar_path
            ?: 'https://ui-avatars.com/api/?name='.urlencode($user->name).($size ? '&size='.$size : '');
    }
}
