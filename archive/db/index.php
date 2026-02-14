<?php
/** get the required file */
require_once "melodic/core.php";

use Melodic\DB\Context;
use Melodic\DB\Query;

$dbContext = new Context([
	"name" => "MelodicTest",
	"drive" => "mysql:host=localhost;dbname=MelodicTest",
	"username" => "rickhopkins",
	"password" => "dH122101",
	"options" => []
]);

$query = new Query($dbContext, sprintf("SELECT * FROM %s", $model->_table));
if ($id != null) $query->statement .= sprintf(" WHERE %s = %d", ($field == null ? $model->_key : $field), $id);

/** execute query */
$results = $query->execute()->asModels(get_class($model));
return (count($results) > 0 ? (count($results) > 1 ? $results : $results[0]) : null);
?>

<html>
<head>
	<title>New Melodic DB Access Layer</title>
</head>
<body>

	Hello There

</body>
</html>
