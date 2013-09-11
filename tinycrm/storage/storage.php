<?php

class Storage
{
    protected static $schema = array('name', 'phone');

    public static function get() {
        return array_map(function ($c) {
            return array_combine(
                self::$schema,
                explode(',', trim($c, "\r\n"))
            );
        }, file(__DIR__ . '/contacts.csv'));
    }

    public static function getJSON() {
        return json_encode(self::get());
    }
}