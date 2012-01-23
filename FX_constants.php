<?php

/*********************************************************************
 * This file is part of the release of FX.php.  This PHP class is    *
 * freely available from http://www.iviking.org/                     *
 *                                                                   *
 * The comments herein are designed to be helpful to someone with    *
 * little or no programming experience.  To that end, many of the    *
 * comments may address things will appear obvious to many coders.   *
 * For the most part I'll place my comments at the end of each line. *
 * Feel free to e-mail any comments or questions to FX@iviking.org.  *
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame:                          *
 * http://www.opensource.org/licenses/artistic-license.html          *
 *********************************************************************/

// these functions are designed to be used with DoFXAction():
// DoFXAction ($currentAction, $returnDataSet = true, $useInnerArray=false, $returnType = 'object')

define("FX_ACTION_DELETE", '-delete');              // -depricated in FX.php use "DELETE"
define("FX_ACTION_DUPLICATE", '-dup');              // -depricated in FX.php use "DUPLICATE"
define("FX_ACTION_EDIT", '-edit');                  // -depricated in FX.php use "UPDATE"
define("FX_ACTION_FIND", '-find');                  // -depricated in FX.php use "PERFORM_FIND"
define("FX_ACTION_FINDALL", '-findall');            // -depricated in FX.php use "SHOW_ALL"
define("FX_ACTION_FINDANY", '-findany');            // -depricated in FX.php use "SHOW_ANY"
define("FX_ACTION_NEW", '-new');                    // -depricated in FX.php use "NEW"

define("FX_ACTION_VIEW", '-view');                  // -depricated in FX.php use "VIEW_LAYOUT_OBJECTS"
define("FX_ACTION_DATABASENAMES", '-dbnames');      // -depricated in FX.php use "VIEW_DATABASE_NAMES"
define("FX_ACTION_LAYOUTNAMES", '-layoutnames');    // -depricated in FX.php use "VIEW_LAYOUT_NAMES"
define("FX_ACTION_SCRIPTNAMES", '-scriptnames');    // -depricated in FX.php use "VIEW_SCRIPT_NAMES"

define("FX_ACTION_OPEN", '-dbopen');                // -depricated in FileMaker
define("FX_ACTION_CLOSE", '-dbclose');              // -depricated in FileMaker

// the following group of constants are designed to be used as the second parameter for DoFXAction()
define("FX_DATA_RETURNED", true);
define("FX_DATA_UNSENT", false);

// the following group of constants are designed to be used as the third parameter for DoFXAction()
define("FX_ARRAY_PORTALS", true);
define("FX_ARRAY_FIELDS", false);

// the following group of constants are designed to be used as the fourth parameter for DoFXAction()
define("FX_RETURN_OBJECT", 'object');
define("FX_RETURN_FULL", 'full');
define("FX_RETURN_BASIC", 'basic');

?>