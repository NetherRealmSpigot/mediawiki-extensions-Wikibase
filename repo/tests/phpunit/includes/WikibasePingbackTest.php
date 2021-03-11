<?php

declare( strict_types=1 );
namespace Wikibase\Repo\Tests;

use Config;
use ExtensionRegistry;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiIntegrationTestCase;
use MWTimestamp;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use TestLogger;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLBFactory;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use Wikibase\Repo\WikibasePingback;
use Wikibase\Repo\WikibaseRepo;

/**
 *
 * @covers \Wikibase\Repo\WikibasePingback
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class WikibasePingbackTest extends MediaWikiIntegrationTestCase {

	private const TEST_KEY = 'TEST_KEY';

	public function setUp(): void {
		parent::setUp();

		global $wgWBRepoSettings;

		$settings = $wgWBRepoSettings;
		$settings['wikibasePingback'] = true;
		$this->setMwGlobals( 'wgWBRepoSettings', $settings );
		WikibaseRepo::resetClassStatics();
		$this->tablesUsed[] = 'updatelog';
	}

	public function testGetSystemInfo() {
		$systemInfo = $this->getPingback()->getSystemInfo();
		$this->assertIsArray( $systemInfo );
	}

	public function testSendPingback() {
		$requestFactory = $this->createMock( HTTPRequestFactory::class );
		$requestFactory->expects( $this->once() )
			->method( 'post' )
			->willReturn( true );

		$pingback = $this->getPingback( $requestFactory );
		$result = $pingback->sendPingback();

		$this->assertTrue( $result );
	}

	public function testGetSystemInfo_getsListOfExtenstions() {
		$pingback = $this->getPingback();
		$actual = $pingback->getSystemInfo()['extensions'];

		$this->assertEquals( [ 'BBL', 'VE' ], $actual );
	}

	public function testGetSystemInfo_getsRepoSettings() {
		$pingback = $this->getPingback();
		$federationActual = $pingback->getSystemInfo()['federation'];
		$termboxActual = $pingback->getSystemInfo()['termbox'];

		$this->assertTrue( $federationActual );
		$this->assertTrue( $termboxActual );
	}

	public function testGetSystemInfo_determinesIfWikibaseHasEntities() {
		$this->populateSiteStats();
		$pingback = $this->getPingback();
		$hasEntities = $pingback->getSystemInfo()['hasEntities'];

		$this->assertTrue( $hasEntities );
	}

	public function testWikibasePingbackSchedules() {
		MWTimestamp::setFakeTime( '20000101010000' );
		$logger = new TestLogger( true );

		$currentTime = $this->getPingbackTime();
		$this->assertFalse( $currentTime );

		// first time there no row - it should pingback as soon as this code is run
		WikibasePingback::doSchedule( $this->getPingbackWithRequestExpectation( $this->once(), $logger ) );

		$currentTime = $this->getPingbackTime();
		$this->assertIsNumeric( $currentTime );

		// this won't trigger it
		WikibasePingback::doSchedule( $this->getPingbackWithRequestExpectation( $this->never(), $logger ) );
		$this->assertSame( $currentTime, $this->getPingbackTime() );

		// move forward one month
		MWTimestamp::setFakeTime( '20000201010000' );

		// should trigger
		WikibasePingback::doSchedule( $this->getPingbackWithRequestExpectation( $this->once(), $logger ) );

		$buffer = $logger->getBuffer();
		$this->assertCount( 2, $buffer );
		$this->assertSame(
			$buffer[0],
			[
				LogLevel::DEBUG,
				'Wikibase\Repo\WikibasePingback::sendPingback: pingback sent OK (' . self::TEST_KEY . ')'
			]
		);
		$this->assertSame(
			$buffer[1],
			[
				LogLevel::DEBUG,
				'Wikibase\Repo\WikibasePingback::sendPingback: pingback sent OK (' . self::TEST_KEY . ')'
			]
		);
		MWTimestamp::setFakeTime( false );
		$logger->clearBuffer();
	}

	private function getPingbackTime() {
		return $this->db->selectField(
			'updatelog',
			'ul_value',
			[ 'ul_key' => self::TEST_KEY ],
			__METHOD__
		);
	}

	public function getPingbackWithRequestExpectation( $expectation, $logger ) {
		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->expects( $expectation )
			->method( 'post' )
			->willReturn( true );

		return new WikibasePingback(
			null,
			$logger,
			null,
			null,
			$requestFactory,
			new FakeLBFactory( [ 'lb' => new FakeLoadBalancer( [ 'dbr' => $this->db ] ) ] ),
			self::TEST_KEY
		);
	}

	private function getPingback(
		HttpRequestFactory $requestFactory = null,
		Config $config = null,
		LoggerInterface $logger = null,
		ExtensionRegistry $extensions = null,
		SettingsArray $wikibaseRepoSettings = null
	): WikibasePingback {
		$config = $config ?: $this->createMock( Config::class );
		$logger = $logger ?: $this->createMock( LoggerInterface::class );
		$extensions = $extensions ?: $this->createMock( ExtensionRegistry::class );
		$wikibaseRepoSettings = $wikibaseRepoSettings ?: $this->createMock( SettingsArray::class );
		$requestFactory = $requestFactory ?: $this->createMock( HTTPRequestFactory::class );
		$loadBalancerFactory = new FakeLBFactory( [ 'lb' => new FakeLoadBalancer( [ 'dbr' => $this->db ] ) ] );

		$wikibaseRepoSettings
			->method( 'getSetting' )
			->withConsecutive( [ 'federatedPropertiesEnabled' ], [ 'termboxEnabled' ] )
			->willReturn( true );

		$extensions->method( 'getAllThings' )
			->willReturn( [
				'Babel' => [],
				'VisualEditor' => []
			] );

		return new WikibasePingback(
			$config,
			$logger,
			$extensions,
			$wikibaseRepoSettings,
			$requestFactory,
			$loadBalancerFactory
		);
	}

	private function populateSiteStats() {
		$this->db->update( 'site_stats', [ 'ss_total_pages' => 11 ], [ 'ss_row_id' => 1 ], __METHOD__ );
	}
}
