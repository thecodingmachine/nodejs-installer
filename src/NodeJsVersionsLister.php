<?php
namespace Mouf\NodeJsInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\RemoteFilesystem;

/**
 * A class in charge of retrieving all the available versions of NodeJS.
 */
class NodeJsVersionsLister
{
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var RemoteFilesystem
     */
    protected $rfs;

    /**
     * @var array
     */
    protected $extra;

    const NODEJS_DIST_URL = "https://nodejs.org/dist/";

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->rfs = new RemoteFilesystem($io, $composer->getConfig());
        $this->extra = $composer->getPackage()->getExtra();
    }

    public function getList()
    {
        $requestOptions = array();

        if (isset($this->extra['mouf']['request_options'])) {
            $requestOptions = $this->extra['mouf']['request_options'];
        }
        
        // Let's download the content of HTML page https://nodejs.org/dist/
        $html = $this->rfs->getContents(parse_url(self::NODEJS_DIST_URL, PHP_URL_HOST), self::NODEJS_DIST_URL, false, $requestOptions);

        // Now, let's parse it!
        $matches = array();
        preg_match_all("$>v([0-9]*\\.[0-9]*\\.[0-9]*)/<$", $html, $matches);

        if (!isset($matches[1])) {
            throw new NodeJsInstallerException("Error while querying ".self::NODEJS_DIST_URL.". Unable to find NodeJS
            versions on this page.");
        }

        return $matches[1];
    }
}
