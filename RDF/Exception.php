<?php
/**
 * RDF_Error implements a class for reporting RDF error
 * messages.
 *
 * @package RDF
 * @category RDF
 * @author  Stig Bakken <ssb@fast.no>
 */
class RDF_Error extends PEAR_Error
{
    // }}}
    // {{{ constructor

    /**
     * RDF_Error constructor.
     *
     * @param mixed   $code      RDF error code, or string with error message.
     * @param integer $mode      what 'error mode' to operate in
     * @param integer $level     what error level to use for
     *                           $mode & PEAR_ERROR_TRIGGER
     * @param smixed  $debuginfo additional debug info, such as the last query
     */
    function RDF_Error($code = RDF_ERROR, $mode = PEAR_ERROR_RETURN,
              $level = E_USER_NOTICE, $debuginfo = null)
    {
        if (is_int($code)) {
            $this->PEAR_Error('RDF Error: '.RDF::errorMessage($code), $code,
                $mode, $level, $debuginfo);
        } else {
            $this->PEAR_Error("RDF Error: $code", RDF_ERROR, $mode, $level,
                $debuginfo);
        }
    }
}
