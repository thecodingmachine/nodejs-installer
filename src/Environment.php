<?php
namespace Mouf\NodeJsInstaller;

/**
 * A class that returns environment related informations (OS, architecture, etc...)
 */
class Environment {
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
     * @return int Returns 32 or 64 depending on supported architecture.
     */
    public static function getArchitecture()
    {
        return 8 * PHP_INT_SIZE;
    }
}