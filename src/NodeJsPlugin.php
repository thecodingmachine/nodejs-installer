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
            'targetDir' => 'vendor/nodejs/nodejs'
        );

        $extra = $event->getComposer()->getPackage()->getExtra();

        if (isset($extra['mouf']['nodejs'])) {
            $rootSettings = $extra['mouf']['nodejs'];
            $settings = array_merge($settings, $rootSettings);
            $settings['version'] = ltrim($settings['version'], 'v');
            $settings['minimumVersion'] = ltrim($settings['minimumVersion'], 'v');
            $settings['targetDir'] = trim($settings['targetDir'], '/\\');
        }

        $this->verboseLog("<info>NodeJS installer:</info>");
        $this->verboseLog(" - Minimum requested version: v".$settings['minimumVersion']);

        $binDir = $event->getComposer()->getConfig()->get('bin-dir');

        $nodeJsInstaller = new NodeJsInstaller($this->io);

        $globalVersion = $nodeJsInstaller->getNodeJsGlobalInstallVersion();
        $isLocal = false;
        if ($globalVersion !== null) {
            $this->verboseLog(" - Global NodeJS install found: v".$globalVersion);

            if (version_compare($globalVersion, $settings['minimumVersion']) === -1) {
                $this->installLocalVersion($nodeJsInstaller, $settings['version'], $settings['minimumVersion'], $settings['targetDir'], $binDir);
                $isLocal = true;
            }
        } else {
            $this->verboseLog(" - No global NodeJS install found");
            $this->installLocalVersion($nodeJsInstaller, $settings['version'], $settings['minimumVersion'], $settings['targetDir'], $binDir);
            $isLocal = true;
        }
        
        // Now, let's create the bin scripts that start node and NPM
        $nodeJsInstaller->createBinScripts($binDir, $settings['targetDir'], $isLocal);
    }

    private function verboseLog($message)
    {
        if ($this->io->isVerbose()) {
            $this->io->write($message);
        }
    }

    private function installLocalVersion(NodeJsInstaller $nodeJsInstaller, $version, $minimumVersion, $targetDir, $binDir)
    {
        $localVersion = $nodeJsInstaller->getNodeJsLocalInstallVersion();
        if ($localVersion !== null) {
            $this->verboseLog(" - Local NodeJS install found: v".$localVersion);

            if (version_compare($localVersion, $minimumVersion) === -1) {
                $nodeJsInstaller->install($version, $targetDir, $binDir);
            }
        } else {
            $this->verboseLog(" - No local NodeJS install found");
            $nodeJsInstaller->install($version, $targetDir, $binDir);
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
