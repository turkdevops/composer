<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Test\Repository;

use Composer\Repository\FilesystemRepository;
use Composer\Test\TestCase;
use Composer\Json\JsonFile;

class FilesystemRepositoryTest extends TestCase
{
    public function testRepositoryRead()
    {
        $json = $this->createJsonFileMock();

        $repository = new FilesystemRepository($json);

        $json
            ->expects($this->once())
            ->method('read')
            ->will($this->returnValue(array(
                array('name' => 'package1', 'version' => '1.0.0-beta', 'type' => 'vendor'),
            )));
        $json
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));

        $packages = $repository->getPackages();

        $this->assertCount(1, $packages);
        $this->assertSame('package1', $packages[0]->getName());
        $this->assertSame('1.0.0.0-beta', $packages[0]->getVersion());
        $this->assertSame('vendor', $packages[0]->getType());
    }

    public function testCorruptedRepositoryFile()
    {
        $this->setExpectedException('Composer\Repository\InvalidRepositoryException');
        $json = $this->createJsonFileMock();

        $repository = new FilesystemRepository($json);

        $json
            ->expects($this->once())
            ->method('read')
            ->will($this->returnValue('foo'));
        $json
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));

        $repository->getPackages();
    }

    public function testUnexistentRepositoryFile()
    {
        $json = $this->createJsonFileMock();

        $repository = new FilesystemRepository($json);

        $json
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(false));

        $this->assertEquals(array(), $repository->getPackages());
    }

    public function testRepositoryWrite()
    {
        $json = $this->createJsonFileMock();

        $repository = new FilesystemRepository($json);
        $im = $this->getMockBuilder('Composer\Installer\InstallationManager')
            ->disableOriginalConstructor()
            ->getMock();
        $im->expects($this->exactly(2))
            ->method('getInstallPath')
            ->will($this->returnValue('/foo/bar/vendor/woop/woop'));

        $json
            ->expects($this->once())
            ->method('read')
            ->will($this->returnValue(array()));
        $json
            ->expects($this->once())
            ->method('getPath')
            ->will($this->returnValue('/foo/bar/vendor/composer/installed.json'));
        $json
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $json
            ->expects($this->once())
            ->method('write')
            ->with(array(
                'packages' => array(
                    array('name' => 'mypkg', 'type' => 'library', 'version' => '0.1.10', 'version_normalized' => '0.1.10.0', 'install-path' => '../woop/woop'),
                    array('name' => 'mypkg2', 'type' => 'library', 'version' => '1.2.3', 'version_normalized' => '1.2.3.0', 'install-path' => '../woop/woop'),
                ),
                'dev' => true,
                'dev-package-names' => array('mypkg2'),
            ));

        $repository->setDevPackageNames(array('mypkg2'));
        $repository->addPackage($this->getPackage('mypkg2', '1.2.3'));
        $repository->addPackage($this->getPackage('mypkg', '0.1.10'));
        $repository->write(true, $im);
    }

    public function testRepositoryWritesInstalledPhp()
    {
        $dir = $this->getUniqueTmpDirectory();
        $json = new JsonFile($dir.'/installed.json');

        $rootPackage = $this->getPackage('__root__', 'dev-master', 'Composer\Package\RootPackage');
        $rootPackage->setSourceReference('sourceref-by-default');
        $rootPackage->setDistReference('distref');
        $this->configureLinks($rootPackage, array('provide' => array('foo/impl' => '2.0')));
        $rootPackage = $this->getAliasPackage($rootPackage, '1.10.x-dev');

        $repository = new FilesystemRepository($json, true, $rootPackage);
        $repository->setDevPackageNames(array('c/c'));
        $pkg = $this->getPackage('a/provider', '1.1');
        $this->configureLinks($pkg, array('provide' => array('foo/impl' => '^1.1', 'foo/impl2' => '2.0')));
        $pkg->setDistReference('distref-as-no-source');
        $repository->addPackage($pkg);

        $pkg = $this->getPackage('a/provider2', '1.2');
        $this->configureLinks($pkg, array('provide' => array('foo/impl' => 'self.version', 'foo/impl2' => '2.0')));
        $pkg->setSourceReference('sourceref');
        $pkg->setDistReference('distref-as-installed-from-dist');
        $pkg->setInstallationSource('dist');
        $repository->addPackage($pkg);

        $repository->addPackage($this->getAliasPackage($pkg, '1.4'));

        $pkg = $this->getPackage('b/replacer', '2.2');
        $this->configureLinks($pkg, array('replace' => array('foo/impl2' => 'self.version', 'foo/replaced' => '^3.0')));
        $repository->addPackage($pkg);

        $pkg = $this->getPackage('c/c', '3.0');
        $repository->addPackage($pkg);

        $im = $this->getMockBuilder('Composer\Installer\InstallationManager')
            ->disableOriginalConstructor()
            ->getMock();
        $im->expects($this->any())
            ->method('getInstallPath')
            ->will($this->returnValue('/foo/bar/vendor/woop/woop'));

        $repository->write(true, $im);
        $this->assertSame(require __DIR__.'/Fixtures/installed.php', require $dir.'/installed.php');
    }

    private function createJsonFileMock()
    {
        return $this->getMockBuilder('Composer\Json\JsonFile')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
