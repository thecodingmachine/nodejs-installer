<?php
namespace Mouf\NodeJsInstaller;

use Composer\Composer;
use Composer\Package\CompletePackage;
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
            'targetDir' => 'vendor/nodejs/nodejs',
            'forceLocal' => false,
            'includeBinInPath' => false,
        );

        if (!class_exists('NodeJsVersionMatcher')) {
            //The package is being uninstalled
            return;
        }
        $nodeJsVersionMatcher = new NodeJsVersionMatcher();

        $extra = $event->getComposer()->getPackage()->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            $settings = array_merge($settings, $rootSettings);
            $settings['targetDir'] = trim($settings['targetDir'], '/\\');
        }

        $versionConstraint = $this->getMergedVersionConstraint();

        $this->verboseLog("<info>NodeJS installer:</info>");
        $this->verboseLog(" - Requested version: ".$versionConstraint);

        $binDir = $event->getComposer()->getConfig()->get('bin-dir');

        $nodeJsInstaller = new NodeJsInstaller($this->io);

        $isLocal = false;

        if ($settings['forceLocal']) {
            $this->verboseLog(" - Forcing local NodeJS install.");
            $this->installLocalVersion($nodeJsInstaller, $versionConstraint, $settings['targetDir']);
            $isLocal = true;
        } else {
            $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();

            if ($globalVersion !== null) {
                $this->verboseLog(" - Global NodeJS install found: v".$globalVersion);

                if (!$nodeJsVersionMatcher->isVersionMatching($globalVersion, $versionConstraint)) {
                    $this->installLocalVersion($nodeJsInstaller, $versionConstraint, $settings['targetDir']);
                    $isLocal = true;
                } else {
                    $this->verboseLog(" - Global NodeJS install matches constraint ".$versionConstraint);
                }
            } else {
                $this->verboseLog(" - No global NodeJS install found");
                $this->installLocalVersion($nodeJsInstaller, $versionConstraint, $settings['targetDir']);
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
     * @param  NodeJsInstaller          $nodeJsInstaller
     * @param  string                   $versionConstraint
     * @param  string                   $targetDir
     * @throws NodeJsInstallerException
     */
    private function installLocalVersion(NodeJsInstaller $nodeJsInstaller, $versionConstraint, $targetDir)
    {
        $nodeJsVersionMatcher = new NodeJsVersionMatcher();

        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion();
        if ($localVersion !== null) {
            $this->verboseLog(" - Local NodeJS install found: v".$localVersion);

            if (!$nodeJsVersionMatcher->isVersionMatching($localVersion, $versionConstraint)) {
                $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir);
            } else {
                // Question: should we update to the latest version? Should we have a nodejs.lock file???
                $this->verboseLog(" - Local NodeJS install matches constraint ".$versionConstraint);
            }
        } else {
            $this->verboseLog(" - No local NodeJS install found");
            $this->installBestPossibleLocalVersion($nodeJsInstaller, $versionConstraint, $targetDir);
        }
    }

    /**
     * Installs locally the best possible NodeJS version matching $versionConstraint
     *
     * @param  NodeJsInstaller          $nodeJsInstaller
     * @param  string                   $versionConstraint
     * @param  string                   $targetDir
     * @throws NodeJsInstallerException
     */
    private function installBestPossibleLocalVersion(NodeJsInstaller $nodeJsInstaller, $versionConstraint, $targetDir)
    {
        $nodeJsVersionsLister = new NodeJsVersionsLister($this->io);
        $allNodeJsVersions = $nodeJsVersionsLister->getList();

        $nodeJsVersionMatcher = new NodeJsVersionMatcher();
        $bestPossibleVersion = $nodeJsVersionMatcher->findBestMatchingVersion($allNodeJsVersions, $versionConstraint);

        if ($bestPossibleVersion === null) {
            throw new NodeJsInstallerNodeVersionException("No NodeJS version could be found for constraint '".$versionConstraint."'");
        }

        $nodeJsInstaller->install($bestPossibleVersion, $targetDir);
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
            /* @var $package PackageInterface */
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
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }
}
