<?php
namespace Mouf\NodeJsInstaller;

use Composer\Package\Version\VersionParser;

/**
 * Tries to find a match between a set of versions and constraint
 */
class NodeJsVersionMatcher
{
    /**
     * Return true if $version matches $constraint (expressed as a Composer constraint string)
     *
     * @param  string $version
     * @param  string $constraint
     * @return bool
     */
    public function isVersionMatching($version, $constraint)
    {
        $versionParser = new VersionParser();

        $normalizedVersion = $versionParser->normalize($version);

        $versionAsContraint = $versionParser->parseConstraints($normalizedVersion);
        $linkConstraint = $versionParser->parseConstraints($constraint);

        return $linkConstraint->matches($versionAsContraint);
    }

    /**
     * Finds the best version matching $constraint.
     * Will return null if no version matches the constraint.
     *
     * @param  array       $versionList
     * @param $constraint
     * @return string|null
     */
    public function findBestMatchingVersion(array $versionList, $constraint)
    {
        // Let's sort versions in reverse order.
        usort($versionList, "version_compare");
        $versionList = array_reverse($versionList);

        // Now, let's find the best match.
        foreach ($versionList as $version) {
            if ($this->isVersionMatching($version, $constraint)) {
                return $version;
            }
        }

        return;
    }
}
