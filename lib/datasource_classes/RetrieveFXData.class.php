<?php

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License (included with release)                    #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

// Do not use this class directly -- it is designed to be appropriately extended
class RetrieveFXData {

    /** @var FX $FX */
    var $FX;

    function __construct(&$FX) {
        $this->FX =& $FX;
    }

    // This method is designed to be replaced by classes extending this one
    function doQuery($action) {
        return new FX_Error("doQuery not implemented.");
    }

    // This method is designed to be replaced by classes extending this one
    function cleanUp() {
        return new FX_Error("cleanUp not implemented.");
    }

}

?>
