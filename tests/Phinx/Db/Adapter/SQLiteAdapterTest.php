<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput,
    Phinx\Db\Adapter\SQLiteAdapter;

class SQLiteAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\MysqlAdapter
     */
    private $adapter;
    
    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED) {
            $this->markTestSkipped('SQLite tests disabled. See TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED constant.');
        }

        $options = array(
            'name' => TESTS_PHINX_DB_ADAPTER_SQLITE_DATABASE
        );
        $this->adapter = new SQLiteAdapter($options, new NullOutput());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase($options['name']);
        $this->adapter->createDatabase($options['name']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }
    
    public function tearDown()
    {
        unset($this->adapter);
    }
    
    public function testConnection()
    {
        $this->assertTrue($this->adapter->getConnection() instanceof \PDO);
    }
 
    public function testCreatingTheSchemaTableOnConnect()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
    }
    
    public function testQuoteTableName()
    {
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }
    
    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }
    
    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('ntable', array(), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new \Phinx\Db\Table('ntable', array('id' => 'custom_id'), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableWithNoOptions()
    {
        $this->markTestIncomplete();
        //$this->adapter->createTable('ntable', )
    }
    
    public function testCreateTableWithNoPrimaryKey()
    {
        $options = array(
            'id' => false
        );
        $table = new \Phinx\Db\Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }
    
    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = array(
            'id'            => false,
            'primary_key'   => array('user_id', 'tag_id')
        );
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->addColumn('tag_id', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('user_id', 'tag_id')));
        $this->assertTrue($this->adapter->hasIndex('table1', array('tag_id', 'USER_ID')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('tag_id', 'user_email')));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('name', 'string')
              ->addIndex('email')
              ->addIndex('name')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('email')));
        $this->assertTrue($this->adapter->hasIndex('table1', array('name')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_email')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_name')));
    }
    
    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', array('unique' => true))
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('email')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_email')));
    }
    
    public function testCreateTableWithMultiplePKsAndUniqueIndexes()
    {
        $this->markTestIncomplete();
    }
    
    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }
    
    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();

        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));

        // In SQLite it is not possible to dictate order of added columns.
        // $table->addColumn('realname', 'string', array('after' => 'id'))
        //       ->save();
        // $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', array('default' => 'test'))
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("test", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', array('default' => 0))
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', array('default' => null))
              ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNull($rows[1]['dflt_value']);
    }
    
    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }
    
    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        
        try {
            $this->adapter->renameColumn('t', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e));
            $this->assertEquals('The specified column doesn\'t exist: column2', $e->getMessage());
        }
    }
    
    public function testChangeColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new \Phinx\Db\Table\Column();
        $newColumn2->setName('column2')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn2);
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('default' => 'test'))
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault('test1')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("test1", $rows[1]['dflt_value']);
    }


    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'integer')
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(0)
                   ->setType('integer');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('default' => 'test'))
              ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(null)
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }
    
    public function testDropColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->dropColumn('t', 'column1');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }

    public function testGetColumns()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->addColumn('column3', 'biginteger')
              ->addColumn('column4', 'text')
              ->addColumn('column5', 'float')
              ->addColumn('column6', 'decimal')
              ->addColumn('column7', 'datetime')
              ->addColumn('column8', 'time')
              ->addColumn('column9', 'timestamp')
              ->addColumn('column10','date')
              ->addColumn('column11', 'binary')
              ->addColumn('column12', 'boolean')
              ->addColumn('column13', 'string', array('limit' => 10))
              ->addColumn('column15', 'integer', array('limit' => 10));
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i+1]);
        }
    }
    
    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
    }
    
    public function testDropIndex()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));
        
        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', array(), $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(array('fname', 'lname'))
               ->save();
        $this->assertTrue($table2->hasIndex(array('fname', 'lname')));
        $this->adapter->dropIndex($table2->getName(), array('fname', 'lname'));
        $this->assertFalse($table2->hasIndex(array('fname', 'lname')));
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('id'));

        $this->adapter->addForeignKey($table, $fk);

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
    }

    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('id'));

        $this->adapter->addForeignKey($table, $fk);
        $this->adapter->dropForeignKey($table->getName(), array('ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
    }
    
    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE));
    }
    
    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('temp_phinx_database'));
        $this->adapter->createDatabase('temp_phinx_database');
        $this->assertTrue($this->adapter->hasDatabase('temp_phinx_database'));
        $this->adapter->dropDatabase('temp_phinx_database');
    }

    public function testAddColumnWithComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('comment' => $comment = 'Comments from "column1"'))
              ->save();

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        foreach($rows as $row) {
            if ($row['tbl_name'] == 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertRegExp('/\/\* Comments from "column1" \*\//', $sql);
    }
}
