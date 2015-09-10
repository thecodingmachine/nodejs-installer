<?php
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

        ob_start();
        $version = exec("nodejs -v 2>&1", $output, $returnCode);
        ob_end_clean();

        if ($returnCode !== 0) {
            ob_start();
            $version = exec("node -v 2>&1", $output, $returnCode);
            ob_end_clean();

            if ($returnCode !== 0) {
                return;
            }
        }

        return ltrim($version, "v");
    }

    /**
     * Returns the full path to NodeJS global install (if available).
     */
    public function getNodeJsGlobalInstallPath()
    {
        $pathToNodeJS = $this->getGlobalInstallPath("nodejs");
        if (!$pathToNodeJS) {
            $pathToNodeJS = $this->getGlobalInstallPath("node");
        }

        return $pathToNodeJS;
    }

    /**
     * Returns the full install path to a command
     * @param string $command
     */
    public function getGlobalInstallPath($command)
    {
        if (Environment::isWindows()) {
            $result = trim(shell_exec("where /F ".escapeshellarg($command)), "\n\r");

            // "Where" can return several lines.
            $lines = explode("\n", $result);

            return $lines[0];
        } else {
            // We want to get output from stdout, not from stderr.
            // Therefore, we use proc_open.
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w"),  // stderr
            );
            $pipes = array();

            $process = proc_open("which ".escapeshellarg($command), $descriptorspec, $pipes);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Let's ignore stderr (it is possible we do not find anything and depending on the OS, stderr will
            // return things or not)
            fclose($pipes[2]);

            proc_close($process);

            return trim($stdout, "\n\r");
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

        if (!Environment::isWindows()) {
            $version = exec("vendor/bin/node -v 2>&1", $output, $returnCode);
        } else {
            $version = exec("vendor\\bin\\node -v 2>&1", $output, $returnCode);
        }

        ob_end_clean();

        chdir($cwd);

        if ($returnCode !== 0) {
            return;
        } else {
            return ltrim($version, "v");
        }
    }

    /**
     * Returns URL based on version.
     * URL is dependent on environment
     * @param  string                   $version
     * @return string
     * @throws NodeJsInstallerException
     */
    public function getNodeJSUrl($version)
    {
        if (Environment::isWindows() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node.exe";
        } elseif (Environment::isWindows() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/x64/node.exe";
        } elseif (Environment::isMacOS() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x86.tar.gz";
        } elseif (Environment::isMacOS() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-darwin-x64.tar.gz";
        } elseif (Environment::isSunOS() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x86.tar.gz";
        } elseif (Environment::isSunOS() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-sunos-x64.tar.gz";
        } elseif (Environment::isLinux() && Environment::isArm()) {
            throw new NodeJsInstallerException('NodeJS-installer cannot install Node on computers with ARM processors. Please install NodeJS globally on your machine first, then run composer again.');
        } elseif (Environment::isLinux() && Environment::getArchitecture() == 32) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-x86.tar.gz";
        } elseif (Environment::isLinux() && Environment::getArchitecture() == 64) {
            return "https://nodejs.org/dist/v".$version."/node-v".$version."-linux-x64.tar.gz";
        } else {
            throw new NodeJsInstallerException('Unsupported architecture: '.PHP_OS.' - '.Environment::getArchitecture().' bits');
        }
    }

    /**
     * Installs NodeJS
     * @param  string                   $version
     * @param  string                   $targetDirectory
     * @throws NodeJsInstallerException
     */
    public function install($version, $targetDirectory)
    {
        $this->io->write("Installing <info>NodeJS v".$version."</info>");
        $url = $this->getNodeJSUrl($version);
        $this->io->write("  Downloading from $url");

        $cwd = getcwd();
        chdir(__DIR__.'/../../../../');

        $fileName = 'vendor/'.pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);

        $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

        if (!file_exists($fileName)) {
            throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
                .' directory is writable and you have internet connectivity');
        }

        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        if (!is_writable($targetDirectory)) {
            throw new NodeJsInstallerException("'$targetDirectory' is not writable");
        }

        if (!Environment::isWindows()) {
            // Now, if we are not in Windows, let's untar.
            $this->extractTo($fileName, $targetDirectory);

            // Let's delete the downloaded file.
            unlink($fileName);
        } else {
            // If we are in Windows, let's move and install NPM.
            rename($fileName, $targetDirectory.'/'.basename($fileName));

            // We have to download the latest available version in a bin for Windows, then upgrade it:
            $url = "https://nodejs.org/dist/npm/npm-1.4.12.zip";
            $npmFileName = "vendor/npm-1.4.12.zip";
            $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $npmFileName);

            $this->unzip($npmFileName, $targetDirectory);

            unlink($npmFileName);

            // Let's update NPM
            // 1- Update PATH to run npm.
            $path = getenv('PATH');
            $newPath = realpath($targetDirectory).";".$path;
            putenv('PATH='.$newPath);

            // 2- Run npm
            $cwd2 = getcwd();
            chdir($targetDirectory);

            $returnCode = 0;
            passthru("npm update npm", $returnCode);
            if ($returnCode !== 0) {
                throw new NodeJsInstallerException("An error occurred while updating NPM to latest version.");
            }

            // Finally, let's copy the base npm file for Cygwin
            if (file_exists('node_modules/npm/bin/npm')) {
                copy('node_modules/npm/bin/npm', 'npm');
            }

            chdir($cwd2);
        }

        chdir($cwd);
    }

    /**
     * Extract tar.gz file to target directory.
     *
     * @param string $tarGzFile
     * @param string $targetDir
     */
    private function extractTo($tarGzFile, $targetDir)
    {
        // Note: we cannot use PharData class because it does not keeps symbolic links.
        // Also, --strip 1 allows us to remove the first directory.

        $output = $return_var = null;

        exec("tar -xvf ".$tarGzFile." -C ".escapeshellarg($targetDir)." --strip 1", $output, $return_var);

        if ($return_var !== 0) {
            throw new NodeJsInstallerException("An error occurred while untaring NodeJS ($tarGzFile) to $targetDir");
        }
    }

    public function createBinScripts($binDir, $targetDir, $isLocal)
    {
        $cwd = getcwd();
        chdir(__DIR__.'/../../../../');

        if (!file_exists($binDir)) {
            $result = mkdir($binDir, 0775, true);
            if ($result === false) {
                throw new NodeJsInstallerException("Unable to create directory ".$binDir);
            }
        }

        $fullTargetDir = realpath($targetDir);
        $binDir = realpath($binDir);

        if (!Environment::isWindows()) {
            $this->createBinScript($binDir, $fullTargetDir, 'node', 'node', $isLocal);
            $this->createBinScript($binDir, $fullTargetDir, 'npm', 'npm', $isLocal);
        } else {
            $this->createBinScript($binDir, $fullTargetDir, 'node.bat', 'node', $isLocal);
            $this->createBinScript($binDir, $fullTargetDir, 'npm.bat', 'npm', $isLocal);
        }

        chdir($cwd);
    }

    /**
     * Copy script into $binDir, replacing PATH with $fullTargetDir
     * @param string $binDir
     * @param string $fullTargetDir
     * @param string $scriptName
     * @param bool   $isLocal
     */
    private function createBinScript($binDir, $fullTargetDir, $scriptName, $target, $isLocal)
    {
        $content = file_get_contents(__DIR__.'/../bin/'.($isLocal ? "local/" : "global/").$scriptName);
        if ($isLocal) {
            $path = $this->makePathRelative($fullTargetDir, $binDir);
        } else {
            if ($scriptName == "node") {
                $path = $this->getNodeJsGlobalInstallPath();
            } else {
                $path = $this->getGlobalInstallPath($target);
            }
        }

        file_put_contents($binDir.'/'.$scriptName, sprintf($content, $path));
        chmod($binDir.'/'.$scriptName, 0755);
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

    private function unzip($zipFileName, $targetDir)
    {
        $zip = new \ZipArchive();
        $res = $zip->open($zipFileName);
        if ($res === true) {
            // extract it to the path we determined above
            $zip->extractTo($targetDir);
            $zip->close();
        } else {
            throw new NodeJsInstallerException("Unable to extract file $zipFileName");
        }
    }

    /**
     * Adds the vendor/bin directory into the path.
     * Note: the vendor/bin is prepended in order to be applied BEFORE an existing install of node.
     *
     * @param string $binDir
     */
    public function registerPath($binDir)
    {
        $path = getenv('PATH');
        if (Environment::isWindows()) {
            putenv('PATH='.realpath($binDir).';'.$path);
        } else {
            putenv('PATH='.realpath($binDir).':'.$path);
        }
    }
}
