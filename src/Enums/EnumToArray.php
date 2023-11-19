<?php

namespace Jobmetric\Flow\Enums;

trait EnumToArray
{
    /**
     * Handle dynamic static method calls.
     *
     * This method is used to handle dynamic static method calls that are not explicitly defined in this class.
     *
     * @param string $name      The name of the method being called.
     * @param array  $arguments The arguments passed to the method.
     *
     * @return mixed|null The result of the method call or null if the method is not found.
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $array = self::arrayValues();
        return $array[$name] ?? null;
    }

    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function array(): array
    {
        return array_combine(self::values(), self::names());
    }

    public static function arrayValues(): array
    {
        return array_combine(self::names(), self::values());
    }
}
