<?php
/**
 * @author Piotr Mrowczynski <Piotr.Mrowczynski@owncloud.com>
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\Files;

use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Test\TestCase;

class BundlingPluginTest extends TestCase {

	/** @var \OC\Files\View | \PHPUnit_Framework_MockObject_MockObject */
	private $view;

	/** @var \OC\Files\FileInfo | \PHPUnit_Framework_MockObject_MockObject */
	private $info;

	/**
	 * @var \Sabre\DAV\Server | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $server;

	/**
	 * @var FilesPlugin
	 */
	private $plugin;

	/**
	 * @var \Sabre\HTTP\RequestInterface | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $request;
	/**
	 * @var \Sabre\HTTP\ResponseInterface | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $response;

	const BOUNDRARY = 'test_boundrary';

	public function setUp() {
		parent::setUp();
		$this->server = new \Sabre\DAV\Server();

		$this->server->tree = $this->getMockBuilder('\Sabre\DAV\Tree')
			->disableOriginalConstructor()
			->getMock();

		$this->view = $this->createMock('OC\Files\View', [], [], '', false);

		$this->info = $this->createMock('OC\Files\FileInfo', [], [], '', false);

		$this->request = $this->getMockBuilder(RequestInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$this->response = $this->getMockBuilder(ResponseInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$this->plugin = new BundlingPlugin(
			$this->view
		);
		$this->plugin->initialize($this->server);
	}

	/*TESTS*/

	/**
	 * This test checks that if url endpoint is wrong, plugin with return exception
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage URL endpoint has to be instance of \OCA\DAV\Files\FilesHome
	 */
	public function testHandleBundleNotHomeCollection() {

		$this->request
			->expects($this->once())
			->method('getPath')
			->will($this->returnValue('notFilesHome.xml'));

		$node = $this->getMockBuilder('\OCA\DAV\Connector\Sabre\File')
			->disableOriginalConstructor()
			->getMock();

		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('notFilesHome.xml')
			->will($this->returnValue($node));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Simulate NULL request header
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Content-Type header is needed
	 */
	public function testHandleBundleNoHeader() {
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue(null));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Simulate empty request header
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Content-Type header must not be empty
	 */
	public function testHandleBundleEmptyHeader() {
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue(""));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Simulate content-type header without boundrary specification request header
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Improper Content-type format. Boundary may be missing
	 */
	public function testHandleBundleNoBoundraryHeader() {
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->atLeastOnce())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue("multipart/related"));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Simulate content-type header with wrong boundrary specification request header
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Boundary is not set
	 */
	public function testHandleBundleWrongBoundraryHeader() {
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->atLeastOnce())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue("multipart/related;thisIsNotBoundrary"));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Simulate content-type header with wrong boundrary specification request header
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Content-Type must be multipart/related
	 */
	public function testHandleBundleWrongContentTypeHeader() {
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->atLeastOnce())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue("multipart/mixed; boundary=".self::BOUNDRARY));

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/**
	 * Request without request body
	 *
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 * @expectedExceptionMessage Unable to get request content
	 */
	public function testHandleBundleWithNullBody() {
		$this->setupServerTillHeader();

		$this->plugin->handleBundle($this->request, $this->response);
	}

	/*UTILITIES*/

	private function setupServerTillHeader(){
		$this->setupServerTillFilesHome();

		$this->request
			->expects($this->atLeastOnce())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue("multipart/related; boundary=".self::BOUNDRARY));
	}

	private function setupServerTillFilesHome(){
		$this->request
			->expects($this->once())
			->method('getPath')
			->will($this->returnValue('files/admin'));

		$node = $this->getMockBuilder('\OCA\DAV\Files\FilesHome')
			->disableOriginalConstructor()
			->getMock();

		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('files/admin')
			->will($this->returnValue($node));
	}
}
