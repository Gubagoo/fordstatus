<?php
ini_set("date.timezone", "America/Toronto");

$db = new PDO("sqlite:/www/fordstatus/database/database.sqlite");

$end = 'NULL';
$last_month = strtotime('last month');

$to = array('ilia.alshanetsky@gubagoo.com', 'rob.edwards@gubagoo.com', 'ryan.osten@gubagoo.com');

$extra = array(
	array('Email Response Time (During Hours)', '', '', 'Within 2 hrs of email receipt'),
	array('Email Response Time (After Business Hours)', '', '', 'Next business day 90% of the time'),
	array('Setup and on-boarding of dealers', '', '', 'Within 5 business days of enrollment'),
	array('Deactivating User Accounts', '', '', 'Deactivate chat accounts within one business day of chat code removal'),
	array('Enrolled Dealer Communication', '', '', 'AM\'s to reach out weekly, minimum of 3 attempted calls'),
	array('Issue Response and Resolution Time', '', '', ''),
	array('Security Requirements', '', '', 'Quarterly items completed'),
	array('Severity 1 issue  (During business Hours, Outside business hours)', '', '', 'Resolution in 15 mins, 30 mins'),
	array('Severity 2 issue  (During business Hours, Outside business hours)', '', '', 'Resolution in 2 hours'),
	array('Severity 3 issue  (During business Hours, Outside business hours)', '', '', 'Resolution in 4 hours'),
	array('Severity 4 issue  (During business Hours, Outside business hours)', '', '', 'Resolution in 8 hours')
);

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
			p.created_at BETWEEN TIMESTAMP(DATE(DATE_SUB(COALESCE({$end}, NOW()), INTERVAL 1 MONTH))) AND TIMESTAMP(DATE(COALESCE({$end}, NOW())))
	  	GROUP BY
	  		m.name, m.suffix";

$conv_sql = "SELECT
					SUM(CASE WHEN stat_name = 'total_chats' THEN value ELSE 0 END) AS total_chats,
					SUM(CASE WHEN stat_name = 'total_chat_leads' THEN value ELSE 0 END) AS total_chat_leads
				FROM gubagoo_enterprise.gg_stats
					WHERE `from` BETWEEN UNIX_TIMESTAMP(DATE(DATE_SUB(COALESCE({$end}, NOW()), INTERVAL 1 MONTH))) AND UNIX_TIMESTAMP(DATE(COALESCE({$end}, NOW())))
					AND account_id IN(SELECT id FROM client_accounts.accounts WHERE deleted=0 AND endorsement = 'Ford Direct')
					AND stat_name IN('total_chats', 'total_chat_leads')";

$conv = $db->query($conv_sql)->fetch(PDO::FETCH_ASSOC);

$fp = fopen("php://memory", "w+");
fputcsv($fp, array("Metric", "Units", "Value", "Target"));

foreach ( $db->query($qry, PDO::FETCH_ASSOC) as $v) {
	switch ($v['suffix']) {
		case 'Response Time (ms)':
			$target = '2 seconds';
			break;
		case '# of Imports':
			$target = '1x';
			break;
		case 'Conversion Ratio (%)':
			$target = 'Average 60 %';
			break;
		case 'Queue Time (secs)':
			if ($v['name'] == 'Support - Phone Queue Time') {
				$target = 'Less than 30 seconds 90% of the time';
			} else {
				$target = 'Average 12 seconds';
			}
			break;
		case 'Response Time (secs)':
			$target = 'Average 60 seconds';
			break;
		default:
			$target = '';
			break;
	}

	if ($v['name'] == 'Chat - Conversion Ratio') {
		$value = round($conv['total_chat_leads'] / $conv['total_chats'] * 100);
		$v['suffix'] = '%';
	} else if (strpos($v['name'], 'Availability') !== false) {
		$v['suffix'] = 'Up-Time (%)';
		$value = $v['avg'] = round($v['avg'] * 100, 2);
	} else if (strpos($v['name'], 'Inventory') !== false) {
		$value = round($v['sum'] / $v['days']);
		$v['suffix'] = '# of Daily Imports';
	} else {
		$v['suffix'] = preg_replace('!^.*\((.+)\).*$!', '\1', $v['suffix']);
		$value = $v['avg'];
	}

	fputcsv($fp, array($v['name'], $v['suffix'], $value, $target));
}

// write general columns
foreach ($extra as $row) {
	fputcsv($fp, $row);
}

fseek($fp, SEEK_SET);
$csv = stream_get_contents($fp);
fclose($fp);

$subject = 'Monthly Ford SLA Report - ' . date("M Y", $last_month);
$content = chunk_split(base64_encode($csv));
$uniqident = md5(uniqid($_SERVER['REQUEST_TIME']));
$filename = "ford_sla_report_" . date("MY", $last_month) . ".csv";

$header  = "From: noreply@gubagoo.com\r\n";
$header .= "MIME-Version: 1.0\r\n";
$header .= "Content-Type: multipart/mixed; boundary=\"".$uniqident."\"\r\n\r\n";
$header .= "This is a multi-part message in MIME format.\r\n";
$header .= "--".$uniqident."\r\n";
$header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
$header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$header .= "Monthly report\r\n\r\n";
$header .= "--".$uniqident."\r\n";
$header .= "Content-Type: text/csv; name=\"".$filename."\"\r\n";
$header .= "Content-Transfer-Encoding: base64\r\n";
$header .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
$header .= $content."\r\n\r\n";
$header .= "--" . $uniqident . "--";

foreach ($to as $recipient) {
	mail($recipient, $subject, "Monthly report", $header);
}