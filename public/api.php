<?php
if (!in_array($_SERVER['REMOTE_ADDR'], gethostbynamel("cron.gubagoo.io"))) {
	exit();
}

ini_set("date.timezone", "America/Toronto");
$db = new PDO("sqlite:/www/fordstatus/database/database.sqlite");

if (!empty($_GET['end']) && $_GET['end'] > 0) {
	$end = (int) $_GET['end'];
} else {
	$end = 'NULL';
}
$last_month = strtotime('last month');

$qry = "SELECT 
			m.name,
			m.suffix,
			ROUND(AVG(value), 2) AS `avg`,
			count(distinct DATE(p.created_at)) AS `days`,
			SUM(value) AS `sum`
		FROM
		 	metric_points p 
		 	INNER JOIN metrics m ON m.id = p.metric_id
		WHERE
			p.created_at BETWEEN  COALESCE({$end}, date('now', '-1 month'))  AND COALESCE({$end}, date('now'))
	  	GROUP BY
	  		m.name, m.suffix";

$data = $db->query($qry)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);
