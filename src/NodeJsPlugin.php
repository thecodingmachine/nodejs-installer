<?php
namespace Mouf\NodeJsInstaller;

use Composer\Composer;
use Composer\Script\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/**
 * This class is the entry point for the NodeJs plugin.
 *
 *
 * @author David Négrier
 */
class NodeJsPlugin implements PluginInterface, EventSubscriberInterface
{

    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Let's register the harmony dependencies update events.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => array(
                array('postAutoloadDump', 0),
            ),
        );
    }

    /**
     * Script callback; Acted on after autoload dump.
     */
    public function postAutoloadDump(Event $event)
    {
        $settings = array(
            'version' => '0.12.0',
            'minimumVersion' => '0.8.0',
        );

        $extra = $event->getComposer()->getPackage()->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            $settings = array_merge($settings, $rootSettings);
            $settings['version'] = ltrim($settings['version'], 'v');
            $settings['minimumVersion'] = ltrim($settings['minimumVersion'], 'v');
        }

        $this->verboseLog("<info>NodeJS installer:</info>");
        $this->verboseLog(" - Minimum requested version: v".$settings['minimumVersion']);

        $nodeJsInstaller = new NodeJsInstaller($this->io);

        $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();
        if ($globalVersion !== null) {
            $this->verboseLog(" - Global NodeJS install found: v".$globalVersion);

            if (version_compare($globalVersion, $settings['minimumVersion']) === -1) {
                $this->installLocalVersion($nodeJsInstaller, $settings['version'], $settings['minimumVersion']);
            }
        } else {
            $this->verboseLog(" - No global NodeJS install found");
            $this->installLocalVersion($nodeJsInstaller, $settings['version'], $settings['minimumVersion']);
        }
    }

    private function verboseLog($message)
    {
        if ($this->io->isVerbose()) {
            $this->io->write($message);
        }
    }

    private function installLocalVersion(NodeJsInstaller $nodeJsInstaller, $version, $minimumVersion)
    {
        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion();
        if ($localVersion !== null) {
            $this->verboseLog(" - Local NodeJS install found: v".$localVersion);

            if (version_compare($localVersion, $minimumVersion) === -1) {
                $nodeJsInstaller->install($version);
            }
        } else {
            $this->verboseLog(" - No local NodeJS install found");
            $nodeJsInstaller->install($version);
        }
    }

    /**
     * Downloads and extracts the package, only if the URL to download has not been downloaded before.
     *
     * @param  PackageInterface          $package
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    private function downloadAndExtractFile(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra['url'])) {
            $url = $extra['url'];

            if (isset($extra['omit-first-directory'])) {
                $omitFirstDirectory = strtolower($extra['omit-first-directory']) == "true";
            } else {
                $omitFirstDirectory = false;
            }

            if (isset($extra['target-dir'])) {
                $targetDir = $extra['target-dir'];
            } else {
                $targetDir = '.';
            }
            $targetDir = './'.trim($targetDir, '/').'/';

            // First, try to detect if the archive has been downloaded
            // If yes, do nothing.
            // If no, let's download the package.
            if (self::getLastDownloadedFileUrl($package) == $url) {
                return;
            }

            // Download (using code from FileDownloader)
            $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

            if (!extension_loaded('openssl') && 0 === strpos($url, 'https:')) {
                throw new \RuntimeException('You must enable the openssl extension to download files via https');
            }

            $this->io->write("    - Downloading <info>".$fileName."</info> from <info>".$url."</info>");

            $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

            if (!file_exists($fileName)) {
                throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
                .' directory is writable and you have internet connectivity');
            }

            // Extract using ZIP downloader
            if ($extension == 'zip') {
                $this->io->write("    - Extracting <info>".$fileName."</info>");
                $this->extractZip($fileName, $targetDir, $omitFirstDirectory);
            } elseif ($extension == 'tar' || $extension == 'gz' || $extension == 'bz2') {
                $this->io->write("    - Extracting <info>".$fileName."</info>");
                $this->extractTgz($fileName, $targetDir, $omitFirstDirectory);
            }

            // Delete archive once download is performed
            unlink($fileName);

            // Save last download URL
            self::setLastDownloadedFileUrl($package, $url);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'archive-package' === $packageType;
    }

    /**
     * Returns the URL of the last file that this install process ever downloaded.
     *
     * @param  PackageInterface $package
     * @return string
     */
    public static function getLastDownloadedFileUrl(PackageInterface $package)
    {
        $packageDir = self::getPackageDir($package);
        if (file_exists($packageDir."download-status.txt")) {
            return file_get_contents($packageDir."download-status.txt");
        } else {
            return;
        }
    }

    /**
     * Saves the URL of the last file that this install process downloaded into a file for later retrieval.
     *
     * @param PackageInterface $package
     * @param unknown          $url
     */
    public static function setLastDownloadedFileUrl(PackageInterface $package, $url)
    {
        $packageDir = self::getPackageDir($package);
        file_put_contents($packageDir."download-status.txt", $url);
    }

    /**
     * Returns the package directory, with a trailing /
     *
     * @param  PackageInterface $package
     * @return string
     */
    public static function getPackageDir(PackageInterface $package)
    {
        return __DIR__."/../../../../../".$package->getName()."/";
    }

    /**
     * Extract ZIP (copied from Composer's ZipDownloader)
     *
     * @param  string                    $file
     * @param  string                    $path
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    protected function extractZip($file, $path, $omitFirstDirectory)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('You need the zip extension enabled to use the ZipDownloader');
        }

        $zipArchive = new \ZipArchive();

        if (true !== ($retval = $zipArchive->open($file))) {
            throw new \UnexpectedValueException("Unable to open downloaded ZIP file.");
        }

        if ($omitFirstDirectory) {
            $this->extractIgnoringFirstDirectory($zipArchive, $path);
        } else {
            if (true !== $zipArchive->extractTo($path)) {
                throw new \RuntimeException("There was an error extracting the ZIP file. Corrupt file?");
            }
        }

        $zipArchive->close();
    }

    /**
     * Extract the ZIP file, but ignores the first directory of the ZIP file.
     * This is useful if you want to extract a ZIP file that contains all the content stored
     * in one directory and that you don't want this directory.
     *
     * @param  unknown    $zipArchive
     * @param  unknown    $path
     * @throws \Exception
     */
    protected function extractIgnoringFirstDirectory($zipArchive, $path)
    {
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $stat = $zipArchive->statIndex($i);

            $filename = $stat['name'];

            $pos = strpos($filename, '/');
            if ($pos !== false) {
                // The file name, without the the directory
                $newfilename = substr($filename, $pos+1);
            } else {
                $newfilename = $filename;
            }

            if (!$newfilename) {
                continue;
            }

            $fp = $zipArchive->getStream($filename);
            if (!$fp) {
                throw new \Exception("Unable to read file $filename from archive.");
            }

            if (!file_exists(dirname($path.$newfilename))) {
                mkdir(dirname($path.$newfilename), 0777, true);
            }

            // If the current file is actually a directory, let's pass.
            if (strrpos($newfilename, '/') == strlen($newfilename)-1) {
                continue;
            }

            $fpWrite = fopen($path.$newfilename, "wb");

            while (!feof($fp)) {
                fwrite($fpWrite, fread($fp, 65536));
            }
        }
    }

    /**
     * Extract tar, tar.gz or tar.bz2 (copied from Composer's TarDownloader)
     *
     * @param string $file
     * @param string $path
     */
    protected function extractTgz($file, $path, $omitFirstDirectory)
    {
        if ($omitFirstDirectory) {
            throw new \Exception("Sorry! The omit-first-directory parameter is currently only supported for ZIP files.");
        }
        // Can throw an UnexpectedValueException
        $archive = new \PharData($file);
        $archive->extractTo($path, null, true);
    }
}
