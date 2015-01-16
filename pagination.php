<?php
ini_set('display_errors',1);  error_reporting(E_ALL);


#########################################################
#
#	File by : PetengDedet (github.com/PetengDedet)
#   ----------------------------------------------
#	$column 	= columnt on databse to select (String)
#	$table 		= Table name (String)
#	$orderby 	= Name of table to shorted by (String)
#	$ordertype 	= ASC or DESC (String) 
#	$perpage 	= Numb of rows to shown every page (Int)
#	$url 		= Variable name of $_GET method, 'page' will be $_GET['page'] 'no' will be $_GET['no'] (String) 
#



function paginate($column = '',$table = '',$orderby = '',$ordertype = 'DESC',$perpage = 0,$url = 'page'){

# call the class file
# initialize the object

	include 'MysqliDb.php';
	$mysqli = new MysqliDb();

# Check if the $_GET value is already set
# Otherwise 1 will be given
# $page used for offset value
	if (isset($_GET[$url])) {
		$page = $_GET[$url];
	}else{
		$page = 1;
	}
	$offset = ($page-1) * $perpage;
	
# Count how many row we have in database
# This is how we know how many page we must have
	$exe 	= $mysqli->rawQuery('SELECT COUNT(*) as total FROM '. $table);
	$numofpage = ceil($exe[0]['total'] / $perpage);

	if ($numofpage>1) {
		echo "<center><ul class='pagination pagination-sm'>";

		if ($page>1) {
			echo "<li><a href='?".$url."=".($page-1)."'>&laquo;</a></li>";
		}

		$activepage = 0;
		for ($num=1; $num <= $numofpage ; $num++) { 
			if ((($num >= $page-3) && ($num <= $page+3)) || ($num==1) || ($num==$numofpage)) {
                if (($activepage == 1)&&($num=2))echo "<li class='disabled'><a href='#'>...</a></li>";
                if (($activepage != ($numofpage-1)) && ($num == $numofpage))echo "<li class='disabled'><a href='#'>...</a></li>";
                if ($num == $page)echo "<li class='active'><a href='#'>".$num."</a></li>";
                else  echo "<li><a href='?".$url."=".$num."'>".$num."</a></li>";
                $activepage=$num;
            }
		}

		if ($page<$numofpage) {
            echo "<li><a href='?".$url."=".($page+1)."'>&raquo;</a></li>";
        }
		echo "</ul></center>";

	}

	$query = $mysqli -> rawQuery('SELECT '.$column.' FROM '.$table.' ORDER BY '.$orderby.' '.$ordertype.' LIMIT '.$offset.','.$perpage);
	foreach ($query as $key => $value) {
		echo "<tr>";
		foreach ($value as $k => $v) {
			echo "<td>";
			print_r($v);
			echo "</td>";
		}
		echo "</tr>";
	}
}

?>
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
	</head>
<body>
	<div class="container">
		<div class="col-md-8">
			<table class="table table-condensed table-hovered">
				<thead>
					<tr>
						<th>Nama</th>
						<th>Id</th>
						<th>Lembaga</th>
					</tr>
				</thead>
				<tbody>
					<?php
						paginate('nama,iduser,NamaLembaga','view_user','iduser','DESC','10','hlm');
					?>
				</tbody>
			</table>
	</div>
</div>
</body>
</html>
