<?php
namespace Mouf\NodeJsInstaller;

use Composer\Composer;
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Script\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

/**
 * This class is the entry point for the NodeJs plugin.
 *
 *
 * @author David Négrier
 */
class NodeJsPlugin implements PluginInterface, EventSubscriberInterface
{
    const NODEJS_TARGET_DIR = 'vendor/nodejs/nodejs';
    const DOWNLOAD_NODEJS_EVENT = 'download-nodejs';

    /**
     * @var Composer
     */
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

    public function deactivate(Composer $composer, IOInterface $io)
    {
        $binDir = $composer->getConfig()->get('bin-dir');
        $this->onDeactivate($binDir);
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        $binDir = $composer->getConfig()->get('bin-dir');
        $targetDir = self::NODEJS_TARGET_DIR;
        $this->onUninstall($binDir, $targetDir);
    }

    /**
     * Let's register the harmony dependencies update events.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onPostUpdateInstall', 1),
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('onPostUpdateInstall', 1),
            ),
            self::DOWNLOAD_NODEJS_EVENT => array(
                array('onPostUpdateInstall', 1)
            )
        );
    }

    /**
     * Script callback; Acted on after install or update.
     */
    public function onPostUpdateInstall(Event $event)
    {
        $settings = array(
            'targetDir' => self::NODEJS_TARGET_DIR,
            'forceLocal' => false,
            'includeBinInPath' => false,
        );

        $extra = $event->getComposer()->getPackage()->getExtra();

        $requestOptions = array();

        if (isset($extra['mouf']['request_options'])) {
            $requestOptions = $extra['mouf']['request_options'];
        }

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            $settings = array_merge($settings, $rootSettings);
            $settings['targetDir'] = trim($settings['targetDir'], '/\\');
        }

        $binDir = $event->getComposer()->getConfig()->get('bin-dir');

        if (!class_exists(__NAMESPACE__.'\\NodeJsVersionMatcher')) {
            //The package is being uninstalled
            $this->onUninstall($binDir, $settings['targetDir']);

            return;
        }

        $nodeJsVersionMatcher = new NodeJsVersionMatcher();

        $versionConstraint = $this->getMergedVersionConstraint();

        $this->verboseLog("<info>NodeJS installer:</info>");
        $this->verboseLog(" - Requested version: ".$versionConstraint);

        $nodeJsInstaller = new NodeJsInstaller($this->io, $this->composer);

        $isLocal = false;

        if ($settings['forceLocal']) {
            $this->verboseLog(" - Forcing local NodeJS install.");
            $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir'], TRUE, $requestOptions);
            $isLocal = true;
        } else {
            $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();

            if ($globalVersion !== null) {
                $this->verboseLog(" - Global NodeJS install found: v".$globalVersion);
                $npmPath = $nodeJsInstaller->getGlobalInstallPath('npm');

                if (!$npmPath) {
                    $this->verboseLog(" - No NPM install found");
                    $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir'], TRUE, $requestOptions);
                    $isLocal = true;
                } elseif (!$nodeJsVersionMatcher->isVersionMatching($globalVersion, $versionConstraint)) {
                    $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir'], TRUE, $requestOptions);
                    $isLocal = true;
                } else {
                    $this->verboseLog(" - Global NodeJS install matches constraint ".$versionConstraint);
                }
            } else {
                $this->verboseLog(" - No global NodeJS install found");
                $this->installLocalVersion($binDir, $nodeJsInstaller, $versionConstraint, $settings['targetDir']);
                $isLocal = true;
            }
        }

        // Now, let's create the bin scripts that start node and NPM
        $nodeJsInstaller->createBinScripts($binDir, $settings['targetDir'], $isLocal);

        // Finally, let's register vendor/bin in the PATH.
        if ($settings['includeBinInPath']) {
            $nodeJsInstaller->registerPath($binDir);
        }
    }

    /**
     * Writes message only in verbose mode.
     * @param string $message
     */
    private function verboseLog($message)
    {
        if ($this->io->isVerbose()) {
            $this->io->write($message);
        }
    }

    /**
     * Checks local NodeJS version, performs install if needed.
     *
     * @param  string                   $binDir
     * @param  NodeJsInstaller          $nodeJsInstaller
     * @param  string                   $versionConstraint
     * @param  string                   $targetDir
     * @param  bool                     $progress
     * @param  array                    $requestOptions
     * @throws NodeJsInstallerException
     */
    private function installLocalVersion($binDir, NodeJsInstaller $nodeJsInstaller, $versionConstraint, $targetDir, $progress = TRUE, $requestOptions = array())
    {
        $nodeJsVersionMatcher = new NodeJsVersionMatcher();

        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion($binDir);
        if ($localVersion !== null) {
            $this->verboseLog(" - Local NodeJS install found: v".$localVersion);

            if (!$nodeJsVersionMatcher->isVersionMatching($localVersion, $versionConstraint)) {
                $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir, $progress, $requestOptions);
            } else {
                // Question: should we update to the latest version? Should we have a nodejs.lock file???
                $this->verboseLog(" - Local NodeJS install matches constraint ".$versionConstraint);
            }
        } else {
            $this->verboseLog(" - No local NodeJS install found");
            $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir, $progress, $requestOptions);
        }
    }

    /**
     * Installs locally the best possible NodeJS version matching $versionConstraint
     *
     * @param  NodeJsInstaller          $nodeJsInstaller
     * @param  string                   $versionConstraint
     * @param  string                   $targetDir
     * @param  bool                     $progress
     * @param  array                    $requestOptions
     * @throws NodeJsInstallerException
     */
    private function installBestPossibleLocalVersion(NodeJsInstaller $nodeJsInstaller, $versionConstraint, $targetDir, $progress = TRUE, $requestOptions = array())
    {
        $nodeJsVersionsLister = new NodeJsVersionsLister($this->io, $this->composer);
        $allNodeJsVersions = $nodeJsVersionsLister->getList();

        $nodeJsVersionMatcher = new NodeJsVersionMatcher();
        $bestPossibleVersion = $nodeJsVersionMatcher->findBestMatchingVersion($allNodeJsVersions, $versionConstraint);

        if ($bestPossibleVersion === null) {
            throw new NodeJsInstallerNodeVersionException("No NodeJS version could be found for constraint '".$versionConstraint."'");
        }

        $nodeJsInstaller->install($bestPossibleVersion, $targetDir, $progress, $requestOptions);
    }

    /**
     * Gets the version constraint from all included packages and merges it into one constraint.
     */
    private function getMergedVersionConstraint()
    {
        $packagesList = $this->composer->getRepositoryManager()->getLocalRepository()
                ->getCanonicalPackages();
        $packagesList[] = $this->composer->getPackage();

        $versions = array();

        foreach ($packagesList as $package) {
            if ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }
            if ($package instanceof CompletePackage) {
                $extra = $package->getExtra();
                if (isset($extra['mouf']['nodejs']['version'])) {
                    $versions[] = $extra['mouf']['nodejs']['version'];
                }
            }
        }

        if (!empty($versions)) {
            return implode(", ", $versions);
        } else {
            return "*";
        }
    }

    /**
     * Uninstalls NodeJS.
     * Note: other classes cannot be loaded here since the package has already been removed.
     */
    private function onUninstall($binDir, $targetDir)
    {
        $fileSystem = new Filesystem();

        if (file_exists($targetDir)) {
            $this->verboseLog("Removing NodeJS local install");

            // Let's remove target directory
            $fileSystem->remove($targetDir);

            $vendorNodeDir = dirname($targetDir);

            if ($fileSystem->isDirEmpty($vendorNodeDir)) {
                $fileSystem->remove($vendorNodeDir);
            }
        }

        $this->onDeactivate($binDir);
    }

    /**
     * Deactivates NodeJS links.
     */
    private function onDeactivate($binDir)
    {
        $fileSystem = new Filesystem();

        // Remove the links.
        $this->verboseLog("Removing NodeJS and NPM links from Composer bin directory");
        foreach (array("node", "npm", "node.bat", "npm.bat") as $file) {
            $realFile = $binDir.DIRECTORY_SEPARATOR.$file;
            if (file_exists($realFile)) {
                $fileSystem->remove($realFile);
            }
        }
    }

}
