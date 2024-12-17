<?php

namespace Wikibase\Lib\Tests\Store\Sql\Terms\Util;

use PHPUnit\Framework\TestCase;
use Wikibase\Lib\Rdbms\RepoDomainDb;
use Wikibase\Lib\Rdbms\RepoDomainTermsDb;
use Wikibase\Lib\Store\Sql\Terms\Util\ReplicaPrimaryAwareRecordIdsAcquirer;
use Wikimedia\Rdbms\DatabaseSqlite;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \Wikibase\Lib\Store\Sql\Terms\Util\ReplicaPrimaryAwareRecordIdsAcquirer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ReplicaPrimaryAwareRecordIdsAcquirerTest extends TestCase {

	private const TABLE_DDL_FILE_PATH = __DIR__ . '/ReplicaPrimaryAwareRecordIdsAcquirerTest_tableDDL.sql';
	private const TABLE_NAME = 'replica_primary_aware_record_ids_acquirer_test';
	private const ID_COLUMN = 'id';

	/**
	 * @var IDatabase
	 */
	private $dbPrimary;

	/**
	 * @var IDatabase
	 */
	private $dbReplica;

	protected function setUp(): void {
		$this->dbPrimary = DatabaseSqlite::newStandaloneInstance( ':memory:' );
		$this->dbPrimary->sourceFile( self::TABLE_DDL_FILE_PATH );

		$this->dbReplica = DatabaseSqlite::newStandaloneInstance( ':memory:' );
		$this->dbReplica->sourceFile( self::TABLE_DDL_FILE_PATH );
	}

	public function testWhenAllRecordsExistInReplica() {
		$records = $this->getTestRecords();

		$this->dbReplica->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->rows( $records )
			->caller( __METHOD__ )
			->execute();
		$this->assertSameRecordsInDb( $records, $this->dbReplica );

		$idsAcquirer = $this->getTestSubjectInstance();
		$acquiredRecordsWithIds = $idsAcquirer->acquireIds( $records );

		$this->assertNoRecordsInDb( $records, $this->dbPrimary );
		$this->assertSameRecordsInDb( $acquiredRecordsWithIds, $this->dbReplica );
	}

	public function testWhenAllRecordsExistInPrimary() {
		$records = $this->getTestRecords();

		$this->dbPrimary->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->rows( $records )
			->caller( __METHOD__ )
			->execute();
		$this->assertSameRecordsInDb( $records, $this->dbPrimary );

		$idsAcquirer = $this->getTestSubjectInstance();
		$acquiredRecordsWithIds = $idsAcquirer->acquireIds( $records );

		$this->assertNoRecordsInDb( $records, $this->dbReplica );
		$this->assertSameRecordsInDb( $acquiredRecordsWithIds, $this->dbPrimary );
	}

	public function testWhenAllRecordsDoNotExistInReplicaOrPrimary() {
		$records = $this->getTestRecordsWithDuplicate();

		$idsAcquirer = $this->getTestSubjectInstance();
		$acquiredRecordsWithIds = $idsAcquirer->acquireIds( $records );

		$this->assertNoRecordsInDb( $records, $this->dbReplica );
		$this->assertSameRecordsInDb( $acquiredRecordsWithIds, $this->dbPrimary );
	}

	public function testWhenSomeRecordsDoNotExistInReplicaButExistInPrimary() {
		$records = $this->getTestRecordsWithDuplicate();

		$recordsInReplica = [ $records[0], $records[1] ];
		$recordsInPrimary = [ $records[2] ];

		$this->dbReplica->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->rows( $recordsInReplica )
			->caller( __METHOD__ )
			->execute();
		$this->assertSameRecordsInDb( $recordsInReplica, $this->dbReplica );

		$this->dbPrimary->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->rows( $recordsInPrimary )
			->caller( __METHOD__ )
			->execute();
		$this->assertSameRecordsInDb( $recordsInPrimary, $this->dbPrimary );

		$idsAcquirer = $this->getTestSubjectInstance();
		$acquiredRecordsWithIds = $idsAcquirer->acquireIds( $records );

		$this->assertSameSize(
			$acquiredRecordsWithIds,
			array_unique( $records, SORT_REGULAR )
		);
		$this->assertSameRecordsInDb( [ $records[3] ], $this->dbPrimary );
		$this->assertNoRecordsInDb( $recordsInReplica, $this->dbPrimary );
		$this->assertNoRecordsInDb( $recordsInPrimary, $this->dbReplica );
	}

	public function testWhenIgnoringReplica() {
		$records = $this->getTestRecords();

		$this->dbReplica->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->rows( $records )
			->caller( __METHOD__ )
			->execute();
		$this->assertSameRecordsInDb( $records, $this->dbReplica );

		$idsAcquirer = $this->getTestSubjectInstance(
			ReplicaPrimaryAwareRecordIdsAcquirer::FLAG_IGNORE_REPLICA );
		$acquiredRecordsWithIds = $idsAcquirer->acquireIds( $records );

		$this->assertSameSize(
			$acquiredRecordsWithIds,
			$records
		);
		$this->assertSameRecordsInDb( $records, $this->dbPrimary );
	}

	private function assertNoRecordsInDb( array $records, IDatabase $db ) {
		$recordsInDbCount = $db->newSelectQueryBuilder()
			->table( self::TABLE_NAME )
			->where( $this->recordsToSelectConditions( $records, $db ) )
			->caller( __METHOD__ )->fetchRowCount();

		$this->assertSame( 0, $recordsInDbCount );
	}

	private function assertSameRecordsInDb( array $records, IDatabase $db ) {
		$recordsInDbCount = $db->newSelectQueryBuilder()
			->table( self::TABLE_NAME )
			->where( $this->recordsToSelectConditions( $records, $db ) )
			->caller( __METHOD__ )->fetchRowCount();

		$this->assertCount( $recordsInDbCount, $records );
	}

	private function recordsToSelectConditions( array $records, IDatabase $db ) {
		$conditionsPairs = [];
		foreach ( $records as $record ) {
			$conditionPairs[] = $db->andExpr( $record );
		}

		return $db->orExpr( $conditionPairs );
	}

	private function getTestSubjectInstance( $flags = 0x0 ) {
		$loadBalancer = new FakeLoadBalancer( [
			'dbr' => $this->dbReplica,
			'dbw' => $this->dbPrimary,
		] );
		$lbFactory = new FakeLBFactory( [ 'lb' => $loadBalancer ] );

		return new ReplicaPrimaryAwareRecordIdsAcquirer(
			new RepoDomainTermsDb( new RepoDomainDb( $lbFactory, $lbFactory->getLocalDomainID() ) ),
			self::TABLE_NAME,
			self::ID_COLUMN,
			$flags
		);
	}

	private function getTestRecords() {
		return [
			[ 'column_value' => 'valueA1', 'column_id' => '1' ],
			[ 'column_value' => 'valueA2', 'column_id' => '2' ],
			[ 'column_value' => 'valueA3', 'column_id' => '3' ],
			[ 'column_value' => 'valueA4', 'column_id' => '4' ],
		];
	}

	private function getTestRecordsWithDuplicate() {
		return [
			[ 'column_value' => 'valueA1', 'column_id' => '1' ],
			[ 'column_value' => 'valueA2', 'column_id' => '2' ],
			[ 'column_value' => 'valueA3', 'column_id' => '3' ],
			[ 'column_value' => 'valueA3', 'column_id' => '3' ],
			[ 'column_value' => 'valueA4', 'column_id' => '4' ],
		];
	}

}
