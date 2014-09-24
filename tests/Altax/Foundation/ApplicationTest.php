<?php
namespace Test\Altax\Foundation;

use Altax\Foundation\Application;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testGetName()
    {
        $app = new Application();
        $this->assertEquals("Altax", $app->getName());
    }

    public function testGetVersionWithCommit()
    {
        $app = new Application();
        $this->assertEquals("4.0.0 - %commit%", $app->getVersionWithCommit());
    }

    public function testRegisterProviders()
    {
        $app = new Application();
        $app->registerProviders([
            'Illuminate\Events\EventServiceProvider',
            'Illuminate\Filesystem\FilesystemServiceProvider',
        ]);
    }
}
