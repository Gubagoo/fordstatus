<?php
if (!in_array($_SERVER['REMOTE_ADDR'], [gethostbyname("servers02.gubagoo.io"), gethostbyname("servers01.gubagoo.io")])) {
    exit();
}

ini_set("date.timezone", "America/Toronto");
$db = new PDO("sqlite:/www/fordstatus/database/database.sqlite");

if (!empty($_GET['end']) && $_GET['end'] > 0) {
    $end = (int)$_GET['end'];
} else {
    $end = 'NULL';
}

if (!empty($_GET['start']) && $_GET['start'] > 0) {
    $start = date('\'Y-m-d\'', (int)$_GET['start']);
} else {
    $start = 'NULL';
}

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
			p.created_at BETWEEN COALESCE({$start}, date('now', '-1 month'))  AND COALESCE({$end}, date('now'))
	  	GROUP BY
	  		m.name, m.suffix";

$data = $db->query($qry)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);
