To utilize this class, first import Mysqldbi.php into your project, and require it.

<pre>
<code>
require_once('Mysqlidb.php');
</code>
</pre>

After that, create a new instance of the class.

<pre>
<code>
$db = new Mysqlidb('host', 'username', 'password', 'databaseName');
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

if ( $db->insert('posts', $insertData) ) echo 'success!';

</code>
</pre>

<h3> Select Query </h3>

<pre>
<code>
$results = $db->get('tableName', 'numberOfRows-optional');
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
$db->where('id', int);
$results = $db->update('tableName', $updateData);
</code>
</pre>

<h3> Delete Query </h3>

<pre>
<code>
$db->where('id', int);
if ( $db->delete('posts') ) echo 'successfully deleted'; 
</code>
</pre>

<h3> Generic Query Method </h3>

<pre>
<code>
$results = $db->query('SELECT * from posts');
print_r($results); // contains array of returned rows
</code>
</pre>

<h3> Where Method </h3>
<p>This method allows you to specify the parameters of the query.</p>
<pre>
<code>
$db->where('id', int);
$db->where('title', string);
$results = $db->get('tableName');
print_r($results); // contains array of returned rows
</code>
</pre>
