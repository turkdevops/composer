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

namespace Composer\Test\Util\Http;

use Composer\Util\Http\ProxyHelper;
use Composer\Util\Http\ProxyManager;
use Composer\Test\TestCase;

class ProxyManagerTest extends TestCase
{
    protected function setUp()
    {
        unset(
            $_SERVER['HTTP_PROXY'],
            $_SERVER['http_proxy'],
            $_SERVER['HTTPS_PROXY'],
            $_SERVER['https_proxy'],
            $_SERVER['NO_PROXY'],
            $_SERVER['no_proxy'],
            $_SERVER['CGI_HTTP_PROXY']
        );
        ProxyManager::reset();
    }

    protected function tearDown()
    {
        unset(
            $_SERVER['HTTP_PROXY'],
            $_SERVER['http_proxy'],
            $_SERVER['HTTPS_PROXY'],
            $_SERVER['https_proxy'],
            $_SERVER['NO_PROXY'],
            $_SERVER['no_proxy'],
            $_SERVER['CGI_HTTP_PROXY']
        );
        ProxyManager::reset();
    }

    public function testInstantiation()
    {
        $originalInstance = ProxyManager::getInstance();
        $this->assertInstanceOf('Composer\Util\Http\ProxyManager', $originalInstance);

        $sameInstance = ProxyManager::getInstance();
        $this->assertTrue($originalInstance === $sameInstance);

        ProxyManager::reset();
        $newInstance = ProxyManager::getInstance();
        $this->assertFalse($sameInstance === $newInstance);
    }

    public function testGetProxyForRequestThrowsOnBadProxyUrl()
    {
        $_SERVER['http_proxy'] = 'localhost';
        $proxyManager = ProxyManager::getInstance();
        $this->setExpectedException('Composer\Downloader\TransportException');
        $proxyManager->getProxyForRequest('http://example.com');
    }

    /**
     * @dataProvider dataRequest
     */
    public function testGetProxyForRequest($server, $url, $expectedUrl, $expectedOptions, $expectedSecure, $expectedMessage)
    {
        $_SERVER = array_merge($_SERVER, $server);
        $proxyManager = ProxyManager::getInstance();

        $proxy = $proxyManager->getProxyForRequest($url);
        $this->assertInstanceOf('Composer\Util\Http\RequestProxy', $proxy);

        $this->assertSame($expectedUrl, $proxy->getUrl());
        $this->assertSame($expectedOptions, $proxy->getContextOptions());
        $this->assertSame($expectedSecure, $proxy->isSecure());

        $message = $proxy->getFormattedUrl();

        if ($expectedMessage) {
            $condition = stripos($message, $expectedMessage) !== false;
        } else {
            $condition = $expectedMessage === $message;
        }

        $this->assertTrue($condition, 'lastProxy check');
    }

    public function dataRequest()
    {
        $server = array(
            'http_proxy' => 'http://user:p%40ss@proxy.com',
            'https_proxy' => 'https://proxy.com:443',
            'no_proxy' => 'other.repo.org',
        );

        // server, url, expectedUrl, expectedOptions, expectedSecure, expectedMessage
        return array(
            array(array(), 'http://repo.org', '', array(), false, ''),
            array($server, 'http://repo.org', 'http://user:p%40ss@proxy.com:80',
                array('http' => array(
                    'proxy' => 'tcp://proxy.com:80',
                    'header' => 'Proxy-Authorization: Basic dXNlcjpwQHNz',
                    'request_fulluri' => true,
                    )
                ),
                false,
                'http://user:***@proxy.com:80',
            ),
            array(
                $server, 'https://repo.org', 'https://proxy.com:443',
                array('http' => array(
                    'proxy' => 'ssl://proxy.com:443',
                    )
                ),
                true,
                'https://proxy.com:443',
            ),
            array($server, 'https://other.repo.org', '', array(), false, 'no_proxy'),
        );
    }

    /**
     * @dataProvider dataStatus
     */
    public function testGetStatus($server, $expectedStatus, $expectedMessage)
    {
        $_SERVER = array_merge($_SERVER, $server);
        $proxyManager = ProxyManager::getInstance();
        $status = $proxyManager->isProxying();
        $message = $proxyManager->getFormattedProxy();

        $this->assertSame($expectedStatus, $status);

        if ($expectedMessage) {
            $condition = stripos($message, $expectedMessage) !== false;
        } else {
            $condition = $expectedMessage === $message;
        }
        $this->assertTrue($condition, 'message check');
    }

    public function dataStatus()
    {
        // server, expectedStatus, expectedMessage
        return array(
            array(array(), false, null),
            array(array('http_proxy' => 'localhost'), false, 'malformed'),
            array(
                array('http_proxy' => 'http://user:p%40ss@proxy.com:80'),
                true,
                'http=http://user:***@proxy.com:80'
            ),
            array(
                array('http_proxy' => 'proxy.com:80', 'https_proxy' => 'proxy.com:80'),
                true,
                'http=proxy.com:80, https=proxy.com:80'
            ),
        );
    }
}
