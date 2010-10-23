<h3>Change Log</h3>

<p>Branch off master</p>

<ul>
	<li>Changed package name to MySqliDb. This is a MySQLi wrapper.</li>
	<li>Added support for multiple dynamicly bound where conditions.</li>
	<li>Added state resetting after each query exicution to allow multiple calls to the same connection instance.</li>
	<li>Added the ability to staticly retreive the current instance.</li>
	<li>Added numRows support to the query method.</li>
	<li>The triggered error in _prepareQuery() now also displays the SQL statment and the mysqli error message.</li>
</ul>