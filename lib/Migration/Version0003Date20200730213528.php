<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Social\Migration;


use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


/**
 * Class Version0003Date20200611000001
 *
 * @package OCA\Social\Migration
 */
class Version0003Date20200730213528 extends SimpleMigrationStep {


	/** @var IDBConnection */
	private $connection;


	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options
	): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->addChunkToTable($schema, 'social_3_stream', '');
		$this->addChunkToTable($schema, 'social_3_stream_act', '_act');
		$this->addChunkToTable($schema, 'social_3_stream_dest', '_dest');

		return $schema;
	}


	/**
	 * @param ISchemaWrapper $schema
	 * @param string $tableName
	 *
	 * @throws SchemaException
	 */
	private function addChunkToTable(ISchemaWrapper $schema, string $tableName, string $indexName) {
		if (!$schema->hasTable($tableName)) {
			return;
		}

		$table = $schema->getTable($tableName);
		if ($table->hasColumn('chunk')) {
			return;
		}

		$table->addColumn(
			'chunk', Type::SMALLINT,
			[
				'default'  => 1,
				'length'   => 1,
				'unsigned' => true
			]
		);
		if (!$table->hasIndex('chunk' . $indexName)) {
			$table->addIndex(['chunk'], 'chunk' . $indexName);
		}
	}


}

