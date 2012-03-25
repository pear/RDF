<?php
// ----------------------------------------------------------------------------------
// Class: RDF_Store_MDB
// ----------------------------------------------------------------------------------
/**
 * Store_MDB is a persistent store of RDF data using relational database technology.
 * Store_MDB uses the MDB Library for PHP (http://pear.php.net/MDB),
 * which allows to connect to multiple databases in a portable manner.
 *
 * @version V0.7
 * @author Radoslaw Oldakowski <radol@gmx.de>
 * @package model
 * @access public
 */
require_once 'PEAR.php';

class RDF_Store_MDB
{
    /**
     * Database connection object
     *
     * @var object ADOConnection
     * @access protected
     */
    var $dbConn;

    /**
     * Set the database connection with the given parameters.
     *
     * @param string $dsn
     * @param string $options
     * @access public
     */
    function RDF_Store_MDB($dsn, $options = null)
    {
        require_once 'MDB.php';
        // create a new connection object
        $this->dbConn = MDB::connect($dsn, $options);
    }

    /**
     * Create tables and indexes for the given database type.
     *
     * @param string $filename
     * @throws PhpError
     * @access public
     */
    function createTables($filename)
    {
        MDB::loadFile('Manager');
        $manager = new MDB_Manager;
        $err = $manager->connect($this->dbConn);
        if(PEAR::isError($err)) {
            throw new RDF_Exception($err->getMessage());
        }
        $err = $manager->updateDatabase(
            $filename,
            $filename.'.old',
            array('database' => $this->dbConn->database_name)
        );
        if(PEAR::isError($err)) {
            throw new RDF_Exception($err->getMessage());
        }
        $dsn = $this->dbConn->getDSN();
        // cant we remove this ugly hack?
        if (isset($dsn['phptype']) && $dsn['phptype'] == 'mysql') {
            $this->dbConn->query('CREATE INDEX s_mod_idx ON statements (modelID)');
            $sql = 'CREATE INDEX s_sub_pred_idx ON statements (subject(200),predicate(200))';
            $this->dbConn->query($sql);
            $this->dbConn->query('CREATE INDEX s_obj_idx ON statements (object(250))');
        }
        return true;
    }

    /**
     * List all Model_MDBs stored in the database.
     *
     * @return array
     * @throws SqlError
     * @access public
     */
    function listModels()
    {
        $sql = 'SELECT modelURI, baseURI FROM models';
        return $this->dbConn->queryAll($sql, MDB_FETCHMODE_ASSOC);
    }

    /**
     * Check if the Model_MDB with the given modelURI is already stored in the database
     *
     * @param string $modelURI
     * @return boolean
     * @throws SqlError
     * @access public
     */
    function modelExists($modelURI)
    {
        $sql = 'SELECT COUNT(*)
            FROM models
            WHERE modelURI = ' . $this->dbConn->getValue('text', $modelURI);
        $result = $this->dbConn->queryOne($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($err->getMessage());
        }

        return (bool)$result;
    }

    /**
     * Create a new instance of Model_MDB with the given $modelURI and
     * load the corresponding values of modelID and baseURI from the database.
     * Return FALSE if the Model_MDB does not exist.
     *
     * @param string $modelURI
     * @return object Model_MDB
     * @access public
     */
    function getModel($modelURI)
    {
        if (!$this->modelExists($modelURI)) {
            return false;
        }

        $sql = 'SELECT modelURI, modelID, baseURI FROM models
            WHERE modelURI=' . $this->dbConn->getValue('text', $modelURI);
        $modelVars = $this->dbConn->queryRow($sql);

        return new RDF_Model_MDB($this->dbConn, $modelVars[0],
            $modelVars[1], $modelVars[2]);
    }

    /**
     * Create a new instance of Model_MDB with the given $modelURI
     * and insert the Model_MDB variables into the database.
     * Return FALSE if there is already a model with the given URI.
     *
     * @param string $modelURI
     * @param string $baseURI
     * @return object Model_MDB
     * @throws SqlError
     * @access public
     */
    function getNewModel($modelURI, $baseURI = null)
    {
        if ($this->modelExists($modelURI)) {
            return false;
        }

        $this->dbConn->autoCommit(false);

        $modelID = $this->_createUniqueModelID();

        $sql = 'INSERT INTO models VALUES (' .
            $this->dbConn->getValue('text', $modelID) .',' .
            $this->dbConn->getValue('text', $modelURI) .',' .
            $this->dbConn->getValue('text', $baseURI) .')';
        $result = $this->dbConn->query($sql);

        $this->dbConn->autoCommit(true);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($err->getMessage());
        }

        return new RDF_Model_MDB($this->dbConn, $modelURI, $modelID, $baseURI);
    }

    /**
     * Store a Model_Memory or another Model_MDB from a different Store_MDB in the database.
     * Return FALSE if there is already a model with modelURI matching the modelURI
     * of the given model.
     *
     * @param object Model  $model
     * @param string $modelURI
     * @return boolean
     * @access public
     */
    function putModel(RDF_Model $model, $modelURI = null)
    {
        if (!$modelURI) {
            if (is_a($model, 'RDF_Model_Memory')) {
                $modelURI = 'Model_MDB-' . $this->_createUniqueModelID();
            } else {
                $modelURI = $model->modelURI;
            }
        } elseif ($this->modelExists($modelURI)) {
            return false;
        }

        $newmodel = $this->getNewModel($modelURI, $model->getBaseURI());
        return $newmodel->addModel($model);
    }

    /**
     * Close the Store_MDB.
     * !!! Warning: If you close the Store_MDB all active instances of Model_MDB from this
     * !!!          Store_MDB will lose their database connection !!!
     *
     * @access public
     */
    function close()
    {
        $this->dbConn->disconnect();
    }

    // =============================================================================
    // **************************** protected methods ********************************
    // =============================================================================
    /**
     * Create a unique ID for the Model_MDB to be insert into the models table.
     * This method was implemented because some databases do not support auto-increment.
     *
     * @return integer
     * @access protected
     */
    function _createUniqueModelID()
    {
        // move to a sequence?
        $sql = 'SELECT MAX(modelID) FROM models';
        $maxModelID = $this->dbConn->queryOne($sql);
        ++$maxModelID;
        return $maxModelID;
    }
} // end: Class Store_MDB
?>
