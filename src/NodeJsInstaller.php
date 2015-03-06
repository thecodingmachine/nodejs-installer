<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 06/03/15
 * Time: 17:01
 */
namespace Mouf\NodeJsInstaller;

use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;

class NodeJsInstaller
{

    /**
     * @var IOInterface
     */
    private $io;

    protected $rfs;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
        $this->rfs = new RemoteFilesystem($io);
    }

    /**
     * Checks if NodeJS is installed globally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsGlobalInstallVersion()
    {
        $returnCode = 0;
        $output = "";
        $version = exec("nodejs -v", $output, $returnCode);

        if ($returnCode != 0) {
            return;
        } else {
            return ltrim($version, "v");
        }
    }

    /**
     * Checks if NodeJS is installed locally.
     * If yes, will return the version number.
     * If no, will return null.
     *
     * Note: trailing "v" will be removed from version string.
     *
     * @return null|string
     */
    public function getNodeJsLocalInstallVersion()
    {
        $returnCode = 0;
        $output = "";

        $cwd = getcwd();
        chdir(__DIR__.'/../../../../');

        ob_start();

        if (!$this->isWindows()) {
            $version = exec("vendor/bin/nodejs -v 2>&1", $output, $returnCode);
        } else {
            $version = exec("vendor\\bin\\nodejs -v 2>&1", $output, $returnCode);
        }

        ob_end_clean();

        chdir($cwd);

        if ($returnCode != 0) {
            return;
        } else {
            return ltrim($version, "v");
        }
    }

    /**
     * @return bool True if OS is Windows.
     */
    private function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @return bool True if OS is MacOSX.
     */
    private function isMacOS()
    {
        return PHP_OS === 'Darwin';
    }

    /**
     * @return bool True if OS is SunOS.
     */
    private function isSunOS()
    {
        return PHP_OS === 'SunOS';
    }

    /**
     * @return bool True if OS is Linux.
     */
    private function isLinux()
    {
        return PHP_OS === 'Linux';
    }

    public function getNodeJSUrl($version)
    {
        if ($this->isWindows() && $this->getArchitecture() == 32) {
            return "http://nodejs.org/dist/v".$version."/node.exe";
        } elseif ($this->isWindows() && $this->getArchitecture() == 64) {
            return "http://nodejs.org/dist/v".$version."/x64/node.exe";
        } elseif ($this->isMacOS() && $this->getArchitecture() == 32) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x86.tar.gz";
        } elseif ($this->isMacOS() && $this->getArchitecture() == 64) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x64.tar.gz";
        } elseif ($this->isSunOS() && $this->getArchitecture() == 32) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x86.tar.gz";
        } elseif ($this->isSunOS() && $this->getArchitecture() == 64) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x64.tar.gz";
        } elseif ($this->isLinux() && $this->getArchitecture() == 32) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-linux-x86.tar.gz";
        } elseif ($this->isLinux() && $this->getArchitecture() == 64) {
            return "http://nodejs.org/dist/v".$version."/node-v".$version."-linux-x64.tar.gz";
        } else {
            throw new NodeJsInstallerException('Unsupported architecture: '.PHP_OS.' - '.$this->getArchitecture().' bits');
        }
    }

    /**
     * @return int Returns 32 or 64 depending on supported architecture.
     */
    public function getArchitecture()
    {
        return 8 * PHP_INT_SIZE;
    }

    /**
     * Installs NodeJS
     * @param $version
     */
    public function install($version)
    {
        $this->io->write("Installing <info>NodeJS v".$version."</info>");
        $url = $this->getNodeJSUrl($version);
        $this->io->write("  Downloading from $url");

        $cwd = getcwd();
        chdir(__DIR__.'/../../../../');

        $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);

        $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

        if (!file_exists($fileName)) {
            throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
                .' directory is writable and you have internet connectivity');
        }

        // Now, if we are not in Windows, let's untar.
        if (!$this->isWindows()) {
            mkdir('vendor/nodejs/nodejs', 0775, true);

            // Can throw an UnexpectedValueException
            $archive = new \PharData($fileName);
            $archive->extractTo('vendor/nodejs/nodejs', null, true);

            // TODO: it seems the links are not kept in bin/npm
        } else {
            // If we are in Windows, let's move and install NPM.
            // TODO
            throw new \Exception("Not implemented yet!");
        }

        chdir($cwd);

        // Let's delete the downloaded file.
        unlink($fileName);
    }
}
