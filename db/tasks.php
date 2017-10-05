<?php

$tasks = array(
	array(
		'classname' => 'report_studentsanalytics\task\StudentProfileAnalytics',
		'blocking' => 0,
		'minute' => '*',
		'hour' => '*/6',
		'day' => '*',
		'dayofweek' => '1-5',
		'month' => '*'
	),
	array(
		'classname' => 'report_studentsanalytics\task\StudentCareerAnalytics',
		'blocking' => 0,
		'minute' => '*',
		'hour' => '*/2',
		'day' => '*',
		'dayofweek' => '1-5',
		'month' => '*'
	)
);