<?php
namespace Mouf\NodeJsInstaller;

/**
 * A class that returns environment related informations (OS, architecture, etc...)
 */
class Environment
{
    /**
     * @return bool True if OS is Windows.
     */
    public static function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @return bool True if OS is MacOSX.
     */
    public static function isMacOS()
    {
        return PHP_OS === 'Darwin';
    }

    /**
     * @return bool True if OS is SunOS.
     */
    public static function isSunOS()
    {
        return PHP_OS === 'SunOS';
    }

    /**
     * @return bool True if OS is Linux.
     */
    public static function isLinux()
    {
        return PHP_OS === 'Linux';
    }

    /**
     * @return bool True if processor is Arm.
     */
    public static function isArm()
    {
        return (strpos(strtolower(php_uname("m")), "arm") === 0 || php_uname("m") === 'aarch64');
    }

    /**
     * @return bool True if processor is Armv7l.
     */
    public static function isArmV7l()
    {
        return php_uname("m") === 'armv7l';
    }

    /**
     * @return bool True if processor is Armv6l.
     */
    public static function isArmV6l()
    {
        return php_uname("m") === 'armv6l';
    }

    /**
     * @return int Returns 32 or 64 depending on supported architecture.
     */
    public static function getArchitecture()
    {
        return 8 * PHP_INT_SIZE;
    }
}
