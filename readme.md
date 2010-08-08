To utilize this class, first import MysqlDb.php into your project, and require it.

<pre>
<code>
require_once('MysqlDb.php');
</code>
</pre>

After that, create a new instance of the class.

<pre>
<code>
$Db = new MysqlDb('host', 'username', 'password', 'databaseName');
</code>
</pre>

Next, prepare your data, and call the necessary methods. 

<h3> Insert Query </h3>
<pre>
<code>
<?php
$insertData = array(
   'title' => 'Inserted title',
    'body' => 'Inserted body'
);

if ( $Db->insert('posts', $insertData) ) echo 'success!';

</code>
</pre>

<h3> Select Query </h3>

<pre>
<code>
$results = $Db->get('tableName', 'numberOfRows-optional');
print_r($results); // contains array of returned rows
</code>
</pre>

<h3> Update Query </h3>

<pre>
<code>
$updateData = array(
   'fieldOne' => 'fieldValue',
    'fieldTwo' => 'fieldValue'
);
$Db->where('id', int);
$results = $Db->update('tableName', $updateData);
</code>
</pre>

<h3> Delete Query </h3>

<pre>
<code>
$Db->where('id', integer);
if ( $Db->delete('posts') ) echo 'successfully deleted'; 
</code>
</pre>

<h3> Generic Query Method </h3>

<pre>
<code>
$results = $Db->query('SELECT * from posts');
print_r($results); // contains array of returned rows
</code>
</pre>
