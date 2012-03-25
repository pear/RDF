<?php
// ----------------------------------------------------------------------------------
// Class: RDF_Model_MDB
// ----------------------------------------------------------------------------------
/**
 * This class provides methods for manipulating Model_MDBs from Store_MDB.
 * A Model_MDB is an RDF Model, which is persistently stored in a relational database.
 * This Class uses MDB Library (http://pear.php.net/MDB).
 *
 * @version V0.7
 * @author Radoslaw Oldakowski <radol@gmx.de>
 * @package model
 * @access public
 */
require_once 'RDF/Model.php';
require_once 'RDF/Exception.php';
require_once 'PEAR.php';
class RDF_Model_MDB extends RDF_Model
{
    /**
     * Database connection object
     *
     * @var object MDB driver instance
     * @access protected
     */
    var $dbConn;

    /**
     * Unique model URI.
     * Used to identify the Model_MDB.
     *
     * @var string
     * @access protected
     */
    var $modelURI;

    /**
     * Database internal modelID.
     * Used to avoid JOINs.
     *
     * @var string
     * @access protected
     */
    var $modelID;

    /**
     * Do not call this directly.
     * Use the method getModel,getNewModel or putModel of the Class Store_MDB instead.
     *
     * @param  object MDB driver instance $dbConnection
     * @param  string $modelURI
     * @param  string $modelID
     * @param  string $baseURI
     * @access public
     */
    function RDF_Model_MDB($dbConnection, $modelURI, $modelID, $baseURI = null)
    {
        $this->dbConn = $dbConnection;
        $this->modelURI = $modelURI;
        $this->modelID = $modelID;
        $this->baseURI = $this->_checkBaseURI($baseURI);
    }

    /**
     * Set a base URI for the Model_MDB.
     * Affects creating of new resources and serialization syntax.
     *
     * @param string $uri
     * @throws SqlError
     * @access public
     */
    function setBaseURI($uri)
    {
        $this->baseURI = $this->_checkBaseURI($uri);

        $sql = 'UPDATE models
            SET baseURI=' . $this->dbConn->getValue('text', $this->baseURI).'
            WHERE modelID=' . $this->dbConn->getValue('integer', $this->modelID);
        return $this->dbConn->query($sql);
    }

    /**
     * Return the number of statements in this Model_MDB
     *
     * @return integer
     * @access public
     */
    function size()
    {
        $sql = 'SELECT COUNT(modelID)
            FROM statements
            WHERE modelID=' . $this->dbConn->getValue('integer', $this->modelID);
        return $this->dbConn->queryOne($sql);
    }

    /**
     * Check if this Model_MDB is empty
     *
     * @return boolean
     * @access public
     */
    function isEmpty()
    {
        if ($this->size() == 0) {
            return true;
        }
        return false;
    }

    /**
     * Add a new triple to this Model_MDB.
     *
     * @param object RDF_Statement    $statement
     * @access public
     */
    function add(RDF_Statement $statement)
    {
        if (!$this->contains($statement)) {
            $subject_is = $this->_getNodeFlag($statement->getSubject());
            $sql = 'INSERT INTO statements VALUES (' .
                $this->dbConn->getValue('integer', $this->modelID) . ',' .
                $this->dbConn->getValue('text', $statement->getLabelSubject()) . ',' .
                $this->dbConn->getValue('text', $statement->getLabelPredicate()). ',';

            if (is_a($statement->getObject(), 'RDF_Literal')) {
                $sql .= $this->dbConn->getValue('text', $statement->obj->getLabel()) . ',' .
                 $this->dbConn->getValue('text', ''.$statement->obj->getLanguage()) . ',' .
                 $this->dbConn->getValue('text', ''.$statement->obj->getDatatype()) . ',' .
                 $this->dbConn->getValue('text', $subject_is) . ',' .
                 $this->dbConn->getValue('text', 'l') . ')';
            } else {
                $sql .= $this->dbConn->getValue('text', $statement->obj->getLabel()) . ',' .
                 $this->dbConn->getValue('text', '') . ',' .
                 $this->dbConn->getValue('text', '') . ',' .
                 $this->dbConn->getValue('text', $subject_is) . ',' .
                 $this->dbConn->getValue('text', $this->_getNodeFlag($statement->getObject())) . ')';
            }

            $result = $this->dbConn->query($sql);
            if (PEAR::isError($result)) {
                throw new RDF_Exception($result->getMessage());
            }
        }
    }

    /**
     * Remove the given triple from this Model_MDB.
     *
     * @param object Statement    $statement
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function remove(RDF_Statement $statement)
    {
        $sql = 'DELETE FROM statements
           WHERE modelID=' . $this->modelID;
        $sql .= $this->_createDynSqlPart_SPO($statement->subj, $statement->pred, $statement->obj);

        return $this->dbConn->query($sql);
    }

    /**
     * Short dump of the Model_MDB.
     *
     * @return string
     * @access public
     */
    function __toString()
    {
        return 'Model_MDB[modelURI=' . $this->modelURI . '; baseURI=' .
            $this->getBaseURI() . ';  size=' . $this->size() . ']';
    }

    /**
     * Dump of the Model_MDB including all triples.
     *
     * @return string
     * @access public
     */
    function toStringIncludingTriples()
    {
        $Model_Memory = $this->getMemModel();
        return $Model_Memory->toStringIncludingTriples();
    }

    /**
     * Create a Model_Memory containing all the triples of the current Model_MDB
     *
     * @return object Model_Memory
     * @access public
     */
    function getMemModel()
    {
        $result = $this->_getRecordSet($this);
        return $this->_convertRecordSetToMemModel($result);
    }

    /**
     * Write the RDF serialization of the _Model_MDB as HTML.
     *
     * @access public
     */
    function writeAsHtml()
    {
        $Model_Memory = $this->getMemModel();
        $Model_Memory->writeAsHtml();
    }

    /**
     * Write the RDF serialization of the Model_MDB as HTML table.
     *
     * @access public
     */
    function writeAsHtmlTable()
    {
        $Model_Memory = $this->getMemModel();
        RDF_Util::writeHTMLTable($Model_Memory);
    }

    /**
     * Write the RDF serialization of the Model_MDB to string
     *
     * @return string
     * @access public
     */
    function writeRDFToString()
    {
        $Model_Memory = $this->getMemModel();
        return $Model_Memory->writeRDFToString();
    }

    /**
     * Saves the RDF,N3 or N-Triple serialization of the Model_MDB to a file.
     * You can decide to which format the model should be serialized by using a
     * corresponding suffix-string as $type parameter. If no $type parameter
     * is placed this method will serialize the model to XML/RDF format.
     * Returns FALSE if the Model_MDB couldn't be saved to the file.
     *
     * @access public 
     * @param string $filename
     * @param string $type
     * @throw PhpError
     * @return boolean
     */  
    function saveAs($filename, $type ='rdf')
    {
        $Model_Memory = $this->getMemModel();
        $Model_Memory->saveAs($filename, $type);
    }

    /**
     * Check if the Model_MDB contains the given statement
     *
     * @param object Statement  $statement
     * @return boolean
     * @access public
     */
    function contains($statement)
    {
        $sql = 'SELECT modelID FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID);
        $sql .= $this->_createDynSqlPart_SPO($statement->subj, $statement->pred, $statement->obj);

        $result = $this->dbConn->queryOne($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        }

        return (bool)$result;
    }

    /**
     * Determine if all of the statements in the given model are also contained in this Model_MDB.
     *
     * @param object Model    $model
     * @return boolean
     * @access public
     */
    function containsAll(RDF_Model $model)
    {
        if (is_a($model, 'RDF_Model_Memory')) {
            foreach($model->triples as $statement) {
                if (!$this->contains($statement)) {
                    return false;
                }
            }
            return true;
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $result = $this->_getRecordSet($model);
            $all = $this->dbConn->fetchAll($result);
            reset($all);
            while (is_array($row = next($all))) {
                if (!$this->_containsRow($row)) {
                    return false;
                }
            }
            return true;
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * Determine if any of the statements in the given model are also contained in this Model_MDB.
     *
     * @param object Model    $model
     * @return boolean
     * @access public
     */
    function containsAny(RDF_Model $model)
    {
        if (is_a($model, 'RDF_Model_Memory')) {
            foreach($model->triples as $statement) {
                if ($this->contains($statement)) {
                    return true;
                }
            }
            return false;
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $result = $this->_getRecordSet($model);
            $all = $this->dbConn->fetchAll($result);
            reset($all);
            while (is_array($row = next($all))) {
                if ($this->_containsRow($row)) {
                    return true;
                }
            }
            return false;
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * General method to search for triples in the Model_MDB.
     * null input for any parameter will match anything.
     * Example:  $result = $m->find( null, null, $node );
     *            Finds all triples with $node as object.
     *
     * @param object Resource $subject
     * @param object Resource $predicate
     * @param object Node     $object
     * @return object Model_Memory
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function find(RDF_Resource $subject = null, RDF_Resource $predicate = null, RDF_Node $object = null)
    {
        // static part of the sql statement
        $sql = 'SELECT subject, predicate, object, l_language, l_datatype, subject_is, object_is
            FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID);
        // dynamic part of the sql statement
        $sql .= $this->_createDynSqlPart_SPO($subject, $predicate, $object);
        // execute the query
        $result = $this->dbConn->query($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        // write the recordSet into memory Model
        }
        return $this->_convertRecordSetToMemModel($result);
    }

    /**
     * Method to search for triples using Perl-style regular expressions.
     * null input for any parameter will match anything.
     * Example:  $result = $m->find_regex( null, null, $regex );
     *            Finds all triples where the label of the object node matches
     * the regular expression.
     * Return an empty Model_Memory if nothing is found.
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * WARNING: Mhis method loads a Model_MDB into memory and performs the search
     *           on a Model_Memory, which can be slow with large models.
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * @param string $subject_regex
     * @param string $predicate_regex
     * @param string $object_regex
     * @return object Model_Memory
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function findRegex($subject_regex, $predicate_regex, $object_regex)
    {
        $mm = $this->getMemModel();

        return $mm->findRegex($subject_regex, $predicate_regex, $object_regex);
    }

    /**
     * Return all tripels of a certain vocabulary.
     * $vocabulary is the namespace of the vocabulary inluding a # : / char at the end.
     * e.g. http://www.w3.org/2000/01/rdf-schema#
     * Return an empty model if nothing is found.
     *
     * @param string $vocabulary
     * @return object Model_Memory
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function findVocabulary($vocabulary)
    {
        $sql = 'SELECT subject, predicate, object, l_language, l_datatype, subject_is, object_is
            FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID) . '
            AND predicate LIKE ' . $this->dbConn->getValue('text', $vocabulary . '%');

        $result = $this->dbConn->query($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        // write the recordSet into memory Model
        }
        return $this->_convertRecordSetToMemModel($result);
    }

    /**
     * Search for triples and return the first matching statement.
     * null input for any parameter will match anything.
     * Return an null if nothing is found.
     *
     * @param object Resource $subject
     * @param object Resource $predicate
     * @param object Node     $object
     * @return object Statement
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function findFirstMatchingStatement(RDF_Resource $subject = null, RDF_Resource $predicate = null, RDF_Node $object = null)
    {
        // static part of the sql statement
        $sql = 'SELECT subject, predicate, object, l_language, l_datatype, subject_is, object_is
            FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID);
        // dynamic part of the sql statement
        $sql .= $this->_createDynSqlPart_SPO($subject, $predicate, $object);
        // execute the query
        $this->dbConn->setSelectedRowRange(0, 1);
        $result = $this->dbConn->query($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        }
        if (!$this->dbConn->numRows($result)) {
            return null;
        }
        $Model_Memory = $this->_convertRecordSetToMemModel($result);
        return $Model_Memory->triples[0];
    }

    /**
     * Search for triples and return the number of matches.
     * null input for any parameter will match anything.
     *
     * @param object Resource $subject
     * @param object Resource $predicate
     * @param object Node     $object
     * @return integer
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function findCount(RDF_Resource $subject = null, RDF_Resource $predicate = null, RDF_Node $object = null)
    {
        // static part of the sql statement
        $sql = 'SELECT COUNT(*)
            FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID);
        // dynamic part of the sql statement
        $sql .= $this->_createDynSqlPart_SPO($subject, $predicate, $object);
        // execute the query
        return $this->dbConn->queryOne($sql);
    }

    /**
     * General method to replace nodes of a Model_MDB.
     * null input for any parameter will match nothing.
     * Example:  $m->replace($resource, null, $node, $replacement);
     *            Replaces all $node objects beeing subject or object in
     *            any triple of the model with the $replacement node.
     * Throw an error in case of a paramter mismatch.
     *
     * @param object Resource $subject
     * @param object Resource $predicate
     * @param object Node     $object
     * @param object Node     $replacement
     * @throws PhpError
     * @throws SqlError
     * @access public
     */
    function replace(RDF_Resource $subject = null, RDF_Resource $predicate = null, RDF_Node $object = null, $replacement)
    {
        // check the correctness of the passed parameters
        if ($subject && is_a($replacement, 'RDF_Literal')) {
            $errmsg = 'Parameter mismatch';
            return throw new InvalidArgumentException($errmsg, RDF_ERROR);
        }


        if ($predicate && (is_a($replacement, 'RDF_Literal') || is_a($replacement, 'RDF_BlankNode'))) {
            $errmsg = 'Parameter mismatch';
            return throw new InvalidArgumentException($errmsg, RDF_ERROR);
        }


        if (!(!$subject && !$predicate && !$object)) {
            // create an update sql statement
            $comma = '';
            $sql = 'UPDATE statements
                SET ';
            if ($subject) {
                $sql .= ' subject =' . $this->dbConn->getValue('text', $replacement->getLabel()) . ', '
                 . ' subject_is=' . $this->dbConn->getValue('text', $this->_getNodeFlag($replacement)) . ' ';
                $comma = ',';
            }
            if ($predicate) {
                $sql .= $comma . ' predicate=' . $this->dbConn->getValue('text', $replacement->getLabel()) . ' ';
                $comma = ',';
            }
            if ($object) {
                $sql .= $comma . ' object=' . $this->dbConn->getValue('text', $replacement->getLabel())
                 . ', object_is=' . $this->dbConn->getValue('text', $this->_getNodeFlag($replacement)) . ' ';
                if (is_a($replacement, 'RDF_Literal')) {
                    $sql .= ', l_language=' . $this->dbConn->getValue('text', ''.$replacement->getLanguage()) . ' '
                     . ', l_datatype=' . $this->dbConn->getValue('text', ''.$replacement->getDataType()) . ' ';
                }
            }
            $sql .= 'WHERE modelID = ' . $this->dbConn->getValue('integer', $this->modelID);
            $sql .= $this->_createDynSqlPart_SPO($subject, $predicate, $object);
            // execute the query
            return $this->dbConn->query($sql);
        }
    }

    /**
     * Check if two models are equal.
     * Two models are equal if and only if the two RDF graphs they represent are isomorphic.
     *
     * Warning: This method doesn't work correct with models where the same blank node has different
     * identifiers in the two models. We will correct this in a future version.
     *
     * @param object model $that
     * @return boolean
     * @throws PhpError
     * @access public
     */

    function equals(RDF_Model $that)
    {
        if ($this->size() != $that->size()) {
            return false;
        }

        $result = $this->containsAll($that);

        if (!$result) {
            return false;
        }
        return true;
    }

    /**
     * Return a new Model_Memory that is the set-union the model with another model.
     *
     * The result of taking the set-union of two or more RDF graphs (i.e. sets of triples)
     * is another graph, which we will call the merge of the graphs.
     * Each of the original graphs is a subgraph of the merged graph. Notice that when forming
     * a merged graph, two occurrences of a given uriref or literal as nodes in two different
     * graphs become a single node in the union graph (since by definition they are the same
     * uriref or literal) but blank nodes are not 'merged' in this way; and arcs are of course
     * never merged. In particular, this means that every blank node in a merged graph can be
     * identified as coming from one particular graph in the original set of graphs.
     *
     * Notice that one does not, in general, obtain the merge of a set of graphs by concatenating
     * their corresponding N-triples documents and constructing the graph described by the merged
     * document, since if some of the documents use the same node identifiers, the merged document
     * will describe a graph in which some of the blank nodes have been 'accidentally' merged.
     * To merge Ntriples documents it is necessary to check if the same nodeID is used in two or
     * more documents, and to replace it with a distinct nodeID in each of them, before merging the
     * documents. (Not implemented yet !!!!!!!!!!!)
     *
     * @param object Model    $model
     * @return object Model_Memory
     * @throws PhpError
     * @access public
     */
    function unite(RDF_Model $model)
    {

        if (is_a($model, 'RDF_Model_Memory')) {
            $thisModel = $this->getMemModel();
            return $thisModel->unite($model);
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $thisModel = $this->getMemModel();
            $thatModel = $model->getMemModel();
            return $thisModel->unite($thatModel);
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * Return a new Model_Memory that is the subtraction of another model from this Model_MDB.
     *
     * @param object Model    $model
     * @return object Model_Memory
     * @throws PhpError
     * @access public
     */

    function subtract(RDF_Model $model)
    {
        if (is_a($model, 'RDF_Model_Memory')) {
            $thisModel = $this->getMemModel();
            return $thisModel->subtract($model);
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $thisModel = $this->getMemModel();
            $thatModel = $model->getMemModel();
            return $thisModel->subtract($thatModel);
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * Return a new Model_Memory containing all the statements which are in both
     * this model and the given model.
     *
     * @param object Model    $model
     * @return object Model_Memory
     * @throws PhpError
     * @access public
     */
    function intersect(RDF_Model $model)
    {
        if (is_a($model, 'RDF_Model_Memory')) {
            $thisModel = $this->getMemModel();
            return $thisModel->intersect($model);
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $thisModel = $this->getMemModel();
            $thatModel = $model->getMemModel();
            return $thisModel->intersect($thatModel);
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * Add the given model to this Model_MDB.
     * This function monitors for SQL errors, and will commit if no errors have occured,
     * otherwise it will rollback.
     * If any statement of the model to be added to this model contains a blankNode 
     * with an identifier already existing in this model, a new blankNode is generated.
     *
     * @param object Model    $model
     * @throw PhpError
     * @access public
     */
    function addModel(RDF_Model $model)
    {
        $blankNodes_tmp = array();

        if (is_a($model, 'RDF_Model_Memory')) {
            $this->dbConn->autoCommit(false);
            foreach ($model->triples as $statement) {
                $result = $this->_addStatementFromAnotherModel($statement, $blankNodes_tmp);

           }
            $this->dbConn->commit();
            $this->dbConn->autoCommit(true);
        } elseif (is_a($model, 'RDF_Model_MDB')) {
            $this->dbConn->autoCommit(false);
            $Model_Memory = $model->getMemModel();
            foreach($Model_Memory->triples as $statement) {
                $result = $this->_addStatementFromAnotherModel($statement, $blankNodes_tmp);

            }
            $this->dbConn->commit();
            $this->dbConn->autoCommit(true);
        }

        throw new RDF_Exception("Unknown model type:" . get_class($model));
    }

    /**
     * Reify the Model_MDB.
     * Return a new Model_Memory that contains the reifications of all statements of this Model_MDB.
     *
     * @return object Model_Memory
     * @access public
     */
    function reify()
    {
        $Model_Memory = $this->getMemModel();
        return $Model_Memory->reify();
    }

    /**
     * Remove this Model_MDB from database and clean up.
     * This function monitors for SQL errors, and will commit if no errors have occured,
     * otherwise it will rollback.
     *
     * @throws SqlError
     * @access public
     */
    function delete()
    {
        $this->dbConn->autoCommit(false);
        $sql = 'DELETE FROM models
            WHERE modelID=' . $this->dbConn->getValue('integer', $this->modelID);
        $this->dbConn->query($sql);
        $sql = 'DELETE FROM statements
            WHERE modelID=' . $this->dbConn->getValue('integer', $this->modelID);
        $this->dbConn->query($sql);

        $result = $this->dbConn->commit();
        $this->dbConn->autoCommit(true);
        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        }
        return $this->close();
    }

    /**
     * Close this Model_MDB
     *
     * @access public
     */
    function close()
    {
    }

    // =============================================================================
    // **************************** protected methods ********************************
    // =============================================================================
    /**
     * Internal method, that returns a resource URI that is unique for the Model_MDB.
     * URIs are generated using the base_uri of the Model_MDB, the prefix and a unique number.
     *
     * @param string $prefix
     * @return string
     * @access protected
     */
    function getUniqueResourceURI($prefix)
    {
        $counter = 1;
        while (true) {
            $uri = $this->getBaseURI() . $prefix . $counter;
            $tempbNode = RDF_BlankNode::factory($uri);

            $res1 = $this->find($tempbNode, null, null);

            $res2 = $this->find(null, null, $tempbNode);

            if ($res1->size() == 0 && $res2->size() == 0) {
                return $uri;
            }
            $counter++;
        }
    }

    /**
     * If the URI doesn't end with # : or /, then a # is added to the URI.
     * Used at setting the baseURI of this Model_MDB.
     *
     * @param string $uri
     * @return string
     * @access protected
     */
    function _checkBaseURI($uri)
    {
        if ($uri != null) {
            $c = substr($uri, strlen($uri)-1 , 1);
            if (!($c == '#' || $c == ':' || $c == '/' || $c == "\\")) {
                $uri .= '#';
            }
        }
        return $uri;
    }

    /**
     * *'
     * Return the flag of the Node object.
     * r - Resource, b - BlankNode, l - Literal
     *
     * @param object Node $object
     * @return string
     * @access protected
     */
    function _getNodeFlag($object)
    {
        return is_a($object,'RDF_BlankNode') ? 'b' : (is_a($object,'RDF_Resource')? 'r' : 'l');
    }

    /**
     * Convert an MDB result to a memory Model.
     *
     * Every successful database query returns an MDB result
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * !!! This method can only be applied to a result with row arrays
     * !!! containing a representation of the database table: statements,
     * !!! with an index corresponding to following table columns:
     * !!! [0] - subject, [1] - predicate, [2] - object, [3] - l_language,
     * !!! [4] - l_datatype, [5] - subject_is, [6] - object_is
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * @param ressource MDB Result
     * @return object Model_Memory
     * @access protected
     */
    function _convertRecordSetToMemModel($result)
    {
        $res = new RDF_Model_Memory($this->getBaseURI());
        $all = $this->dbConn->fetchAll($result);
        reset($all);
        while (is_array($row = next($all))) {
            // subject
            if ($row[5] == 'r') {
                $sub = RDF_Resource::factory($row[0]);
            } else {
                $sub = RDF_BlankNode::factory($row[0]);
            }

            // predicate
            $pred = RDF_Resource::factory($row[1]);

            // object
            if ($row[6] == 'r') {
                $obj = RDF_Resource::factory($row[2]);

            } elseif ($row[6] == 'b') {
                $obj = RDF_BlankNode::factory($row[2]);
            } else {
                $obj = RDF_Literal::factory($row[2], $row[3]);

                if ($row[4]) {
                    $obj->setDatatype($row[4]);
                }
            }
            $statement = RDF_Statement::factory($sub, $pred, $obj);

            $result = $res->add($statement);
        }
        $this->dbConn->freeResult($result);
        return $res;
    }

    /**
     * Create the dynamic part of an sql statement selecting triples with the
     * given parameters ($subject, $predicate, $object).
     *
     * @param object Resource $subject
     * @param object Resource $predicate
     * @param object Node     $object
     * @return string
     * @access protected
     */
    function _createDynSqlPart_SPO($subject, $predicate, $object)
    {
        // conditions derived from the parameters passed to the function
        $sql = '';
        if ($subject != null) {
            $sql .= ' AND subject=' . $this->dbConn->getValue('text', $subject->getLabel()) . '
                AND subject_is=' . $this->dbConn->getValue('text', $this->_getNodeFlag($subject));
        }
        if ($predicate != null) {
            $sql .= ' AND predicate=' . $this->dbConn->getValue('text', $predicate->getLabel());
        }
        if ($object != null) {
            $object_is = $this->_getNodeFlag($object);
            if (is_a($object, 'RDF_Resource')) {
                $sql .= ' AND object=' . $this->dbConn->getValue('text', $object->getLabel()) .'
                     AND object_is =' . $this->dbConn->getValue('text', $object_is);
            } else {
                $sql .= ' AND object=' . $this->dbConn->getValue('text', $object->getLabel()) .'
                     AND l_language=' . $this->dbConn->getValue('text', ''.$object->getLanguage()) . '
                     AND l_datatype=' . $this->dbConn->getValue('text', ''.$object->getDataType()) . '
                     AND object_is =' . $this->dbConn->getValue('text', $object_is);
            }
        }
        return $sql;
    }

    /**
     * Get an MDB result with row arrays containing a representation of
     * the given Model_MDB stored in the table: statements, with an index corresponding
     * to following table columns:
     * [0] - subject, [1] - predicate, [2] - object, [3] - l_language,
     * [4] - l_datatype, [5] - subject_is, [6] - object_is
     * (This method operates on data from a Model_MDB without loading it into a memory model
     *   in order to save resources and improve speed).
     *
     * @param object Model_MDB  $Model_MDB
     * @return resource MDB result
     * @access protected
     */
    function _getRecordSet($model)
    {
        $sql = 'SELECT subject, predicate, object, l_language, l_datatype, subject_is, object_is
            FROM statements
            WHERE modelID = ' . $this->dbConn->getValue('integer', $model->modelID);

        return $this->dbConn->query($sql);
    }

    /**
     * Check if this Model_MDB contains the given row from the row array fields[] of an MDB result
     * The array index corresponds to following table columns:
     * [0] - subject, [1] - predicate, [2] - object, [3] - l_language,
     * [4] - l_datatype, [5] - subject_is, [6] - object_is
     *
     * @param array $row
     * @return boolean
     * @access protected
     */
    function _containsRow($row)
    {
        $sql = 'SELECT modelID FROM statements
            WHERE modelID =' . $this->dbConn->getValue('integer', $this->modelID) . '
                AND subject =' . $this->dbConn->getValue('text', $row[0]) . '
                AND predicate =' . $this->dbConn->getValue('text', $row[1]) . '
                AND object =' . $this->dbConn->getValue('text', $row[2]) . '
                AND l_language=' . $this->dbConn->getValue('text', ''.$row[3]) . '
                AND l_datatype=' . $this->dbConn->getValue('text', ''.$row[4]) . '
                AND subject_is=' . $this->dbConn->getValue('text', $row[5]) . '
                AND object_is=' . $this->dbConn->getValue('text', $row[6]);

        $result = $this->dbConn->queryOne($sql);

        if (PEAR::isError($result)) {
            throw new RDF_Exception($result->getMessage());
        }
        return (bool)$result;
    }

    /**
     * Add the given row from the row array of an MDB result to this Model_MDB
     * The array index corresponds to following table columns:
     * [0] - subject, [1] - predicate, [2] - object, [3] - l_language,
     * [4] - l_datatype, [5] - subject_is, [6] - object_is
     *
     * @param array $row
     * @throws SqlError
     * @access protected
     */
    function _insertRow ($row)
    {
        $sql = 'INSERT INTO statements VALUES (' .
            $this->dbConn->getValue('integer', $this->modelID) . ',' .
            $this->dbConn->getValue('text', $row[0]) . ',' .
            $this->dbConn->getValue('text', $row[1]) . ',' .
            $this->dbConn->getValue('text', $row[2]) . ',' .
            $this->dbConn->getValue('text', $row[3]) . ',' .
            $this->dbConn->getValue('text', $row[4]) . ',' .
            $this->dbConn->getValue('text', $row[5]) . ',' .
            $this->dbConn->getValue('text', $row[6]) . ')';

        return $this->dbConn->query($sql);
    }
} // end: Class Model_MDB
?>
