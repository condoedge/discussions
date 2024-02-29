<?php

if (!function_exists('_UserDate')) {
    function _UserDate($name, $date)
    {
        return _Flex(
            _Html($name)->class('text-sm font-semibold text-gray-500'),
            _DiffDate($date)
        )->class('space-x-2');
    }
}