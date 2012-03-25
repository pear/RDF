<?php
/**
 * An abstract object.
 * Root object with some general methods, that should be overloaded. 
 * 
 * @version V0.7
 * @author Chris Bizer <chris@bizer.de> 
 * @abstract 
 * @package util
 */
class RDF_Object
{
    function factory()
    {
        throw new RDF_Exception('Not implemented', RDF_ERROR);
    }

    /**
     * Serializes a object into a string
     * 
     * @access public 
     * @return string 
     */
    function toString()
    {
        $objectvars = get_object_vars($this);
        foreach($objectvars as $key => $value) {
            $content.= $key . "='" . $value . "'; ";
        }
        return 'Instance of ' . get_class($this) . '; Properties: ' . $content;
    }


    public function __toString() {
        return $this->toString();
    }
}

