# FX.php

A FREE, Open Source PHP database abstraction class for accessing FileMaker Pro and other data sources
by Chris Hansen with Chris Adams, Gjermund Gusland Thorsen, Masayuki Nii, and others.

FX.php is a PHP class originally conceived as a way to easily parse the XML output by FileMaker Pro's XML RPC (A.K.A. Web Publishing Engine or Custom Web Publishing) into a multi-level array which could easily be manipulated using PHP.  Full CRUD -- records (rows in SQL parlance) can be searched, edited, created, and deleted.  In addition, a number of other actions can be performed including script execution, etc.  (See the documentation for more details.)  When any action is performed, the returned array is organized in up to four arrays, as follows (these are the relevant indexes or key values):

* Level 1: (optional, depending on the return type)

    'linkNext', 'linkPrevious', 'foundCount', 'fields', 'data', 'URL', 'errorCode', 'valueLists'

* Level 2: ( of 'data' )

    RecordID.ModificationID

* Level 3:

    Field (Column) Name

* Level 4: (optional, depending on the $useInnerArray parameter)

    Numbers, starting at zero; one for each related or repeating value in the found set

So, a reference to a specific value will be structured like one of the following:

* $dataArray['12.3']['First_Name']
* $dataArray['12.3']['First_Name'][0]
* $dataArray['data']['12.3']['First_Name'][0]

Look at the sample code to get a better feel for how things work.  You can also see it at work on [my site]( http://www.iviking.org/FX.php/ )


FileMaker Pro has quite a following, and with good reason:  it mixes the power of a relational database with phenomenal ease-of-use.  It scales better than similar solutions like Microsoft Access, while providing tools for creating elegant interfaces.

PHP is a free, open-source scripting language in use on around 80% of all web servers. PHP can access just about any data source, but there was no easy way for it to pull data from FileMaker until 2001.  FX.php was the first PHP class which allowed FileMaker enthusiasts to access their data via PHP.  More recent versions oF FX have also added support for MySQL, PostgreSQL, OpenBase, ODBC data sources, and more.  FileMaker, Inc. released its own API (the FileMaker API for PHP) in 2006, but many programmers continue to prefer FX.php for it's simplicity, superior performance, and more frequent updates.

