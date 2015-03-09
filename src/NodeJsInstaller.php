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
     * @param string $version
     * @param string $targetDirectory
     * @throws NodeJsInstallerException
     */
    public function install($version, $targetDirectory, $binDir)
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
            if (!file_exists($targetDirectory)) {
                mkdir($targetDirectory, 0775, true);
            }

            if (!is_writable($targetDirectory)) {
                throw new NodeJsInstallerException("'$targetDirectory' is not writable");
            }

            $this->extractTo($fileName, $targetDirectory);
        } else {
            // If we are in Windows, let's move and install NPM.
            // TODO
            throw new \Exception("Not implemented yet!");
        }

        // Let's delete the downloaded file.
        unlink($fileName);

        // Now, let's create the bin scripts that start node and NPM
        $this->createBinScripts($binDir, $targetDirectory);

        chdir($cwd);
    }

    /**
     * Extract tar.gz file to target directory.
     *
     * @param string $tarGzFile
     * @param string $targetDir
     */
    private function extractTo($tarGzFile, $targetDir) {
        // Note: we cannot use PharData class because it does not keeps symbolic links.
        // Also, --strip 1 allows us to remove the first directory.

        $output = $return_var = null;

        exec("tar -xvf ".$tarGzFile." -C ".escapeshellarg($targetDir)." --strip 1", $output, $return_var);

        if ($return_var != 0) {
            throw new NodeJsInstallerException("An error occurred while untaring NodeJS ($tarGzFile) to $targetDir");
        }
    }

    private function createBinScripts($binDir, $targetDir) {
        $fullTargetDir = realpath($targetDir);

        $content = file_get_contents(__DIR__.'/../bin/node');
        $path = $this->makePathRelative($fullTargetDir, $binDir);
        file_put_contents($binDir.'/node', sprintf($content, $path));
        chmod($binDir.'/node', 0755);

        $content = file_get_contents(__DIR__.'/../bin/npm');
        $path = $this->makePathRelative($fullTargetDir, $binDir);
        file_put_contents($binDir.'/npm', sprintf($content, $path));
        chmod($binDir.'/npm', 0755);
    }

    /**
     * Shamelessly stolen from Symfony's FileSystem. Thanks guys!
     * Given an existing path, convert it to a path relative to a given starting path.
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    private function makePathRelative($endPath, $startPath)
    {
        // Normalize separators on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $endPath = strtr($endPath, '\\', '/');
            $startPath = strtr($startPath, '\\', '/');
        }
        // Split the paths into arrays
        $startPathArr = explode('/', trim($startPath, '/'));
        $endPathArr = explode('/', trim($endPath, '/'));
        // Find for which directory the common path stops
        $index = 0;
        while (isset($startPathArr[$index]) && isset($endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            $index++;
        }
        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        $depth = count($startPathArr) - $index;
        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);
        $endPathRemainder = implode('/', array_slice($endPathArr, $index));
        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser.(strlen($endPathRemainder) > 0 ? $endPathRemainder.'/' : '');
        return (strlen($relativePath) === 0) ? './' : $relativePath;
    }
}
