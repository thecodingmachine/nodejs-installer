<?php
namespace Mouf\NodeJsInstaller;

class NodeJsVersionMatcherTest extends \PHPUnit\Framework\TestCase
{

    public function testIsVersionMatching()
    {
        $matcher = new NodeJsVersionMatcher();
        $this->assertTrue($matcher->isVersionMatching('0.12.0', '~0.11'));
        $this->assertFalse($matcher->isVersionMatching('0.12.0', '~0.11.0'));
        $this->assertTrue($matcher->isVersionMatching('0.12.0', '~0.11, <1.0'));
        $this->assertFalse($matcher->isVersionMatching('0.12.0', '~0.11, <1.0, >1.1'));
    }

    public function testFindBestMatchingVersion()
    {
        $matcher = new NodeJsVersionMatcher();
        $this->assertEquals("0.12.0", $matcher->findBestMatchingVersion(['0.11.1', '0.12.0', '0.10', '0.11.5'], '~0.11'));
    }
}
