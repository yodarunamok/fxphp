# FX.php

A FREE, Open Source PHP database abstraction class for accessing FileMaker Pro and other databases
by Chris Hansen with Chris Adams, Gjermund Gusland Thorsen, Masayuki Nii, and others.

FileMaker Pro has quite a following, and with good reason:  it mixes the power of a relational database with phenomenal ease-of-use.  PHP is a free, embedded scripting language in use on nearly half of all Apache web servers( which themselves account for about 60% of web servers. ) PHP can access just about any data source, but there was no easy way for it to pull data from FileMaker until 2001.  FX.php was the first PHP class which allowed FileMaker enthusiasts to access their data via PHP.  More recent versions have also added support for MySQL, PostgreSQL, OpenBase, ODBC data sources, and more.

FX.php is a PHP class which parses the XML output by FileMaker Pro's XML RPC aka Web Publishing Engine/Custom Web Publishing into a multi-level array which is easily manipulated using PHP.  Full CRUD; Records can be searched, edited, created, and deleted.  In addition, a number of other actions can be performed including script execution, etc.  ( See the documentation for more details. )  When any action is performed, the returned array is organized in up to four arrays, as follows ( these are the relevant indexes or key values ):

* Level 1: ( optional, depending on the return type )

    'linkNext', 'linkPrevious', 'foundCount', 'fields', 'data', 'URL', 'errorCode', 'valueLists'

* Level 2: ( of 'data' )

    RecordID.ModificationID

* Level 3:

    fieldName

* Level 4: ( optional, depending on the return type )

    Numbers, starting at zero; one for each related or repeating value

So, a reference to a specific value will be structured like one of the following:

* $DataArray['12.3']['First_Name']
* $DataArray['data']['12.3']['First_Name'][0]

Look at the sample code to get a better feel for how things work.  You can also see it at work on [my site]( http://www.iviking.org/FX.php/ )
