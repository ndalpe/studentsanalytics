<?php
namespace report_studentsanalytics\task;

class StudentProfileAnalytics extends \core\task\scheduled_task {

	public $chart_colors = array("#008E94","#EB6E7F","#E8CF00","#E34629","#42AD28","#429E98","#30357D","#99362B","#C7AEBC");

	public function get_name() {
		// Shown in admin screens
		return get_string('pluginname', 'report_studentsanalytics');
	}

	public function execute() {
		// Gender
		$this->studentAnalyticGender();
		// Nationality
		$this->studentAnalyticNationality();
		// Age
		$this->studentAnalyticAge();
	}

	///////////////
	// Gender
	///////////////
	public function studentAnalyticGender() {
		if (!isset($DB)) {
			global $DB;
		}

		$m = $DB->get_record_sql("SELECT count(*) as g FROM moodle.mdl_user_info_data WHERE fieldid = 2 AND data = 'Male';");
		$f = $DB->get_record_sql("SELECT count(*) as g FROM moodle.mdl_user_info_data WHERE fieldid = 2 AND data = 'Female';");

		// total population
		$gTotal = $m->g + $f->g;

		// Calc %
		$tM = ($m->g == 0) ? '0' : round(($m->g / $gTotal) * 100);
		$tF = ($f->g == 0) ? '0' : round(($f->g / $gTotal) * 100);

		// Generate the Graph's data
		$json = $this->makeJSON(array(
			'labels' => array('Male', 'Female'),
			'data' => array($tM, $tF)
		));

		// render the graph's data to flat file
		$this->generateFile('studentAnalyticGender_data.json', $json);
	}

	///////////////
	// Nationality
	///////////////
	public function studentAnalyticNationality(){
		if (!isset($DB)) {
			global $DB;
		}

		$cSql = "SELECT data, count(data) as c FROM moodle.mdl_user_info_data WHERE fieldid = 3 GROUP BY data ORDER BY data;";
		$c = $DB->get_records_sql($cSql, null, $limitfrom=0, $limitnum=0);

		foreach ($c as $key => $value) {
			if (empty($value->data)) {
				$labels[] = 'Not set';
			} else {
				$labels[] = $value->data;
			}
			$data[] = $value->c;
		}

		// Generate the Graph's data
		$json = $this->makeJSON(array('labels'=>$labels, 'data'=>$data));

		$this->generateFile('studentAnalyticNationality_data.json', $json);
	}

	///////////////
	// Age
	///////////////
	public function studentAnalyticAge() {
		if (!isset($DB)) {
			global $DB;
		}

		$aSql = "
		SELECT a.timefinish, u.data as dob, DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '%Y') - DATE_FORMAT(FROM_UNIXTIME(u.data), '%Y') - (DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '00-%m-%d') < DATE_FORMAT(FROM_UNIXTIME(u.data), '00-%m-%d')) AS age
		FROM moodle.mdl_quiz_attempts AS a
		INNER JOIN moodle.mdl_user_info_data AS u ON a.userid = u.userid
		WHERE a.quiz = 12 AND a.state = 'finished' AND u.fieldid = 1;";
		$a = $DB->get_records_sql($aSql, null, $limitfrom=0, $limitnum=0);

		// num of student who received a grade for final test
		$ageCount = count($a);

		// Create the age group (20, 25, 30, 35, etc)
		$ageRange = range(15, 75, 5);

		$ageGroups = array();

		foreach ($a as $k => $v) {
			foreach ($ageRange as $ak => $av) {
				// break at last age range (can't do +1 on last element)
				if (count($ageRange) == $ak){break;}
				// next age group key
				$nav = $ak + 1;
				if ($v->age >= $av && $v->age < $ageRange[$nav]) {
					if (isset($ageGroups[$av.'-'.$ageRange[$nav]])) {
						$ageGroups[$av.'-'.$ageRange[$nav]]++;
					} else {
						$ageGroups[$av.'-'.$ageRange[$nav]] = 1;
					}
					// once the student is placed in an age group, break the loop;
					break;
				}
			}
		}

		foreach ($ageGroups as $k => $v) {
			$labels[] = $k;
			$data[] = round(($v / $ageCount) * 100);
		}

		// Generate the Graph's data
		$json = $this->makeJSON(array('labels'=>$labels, 'data'=>$data));

		$this->generateFile('studentAnalyticAge_data.json', $json);
	}

	private function makeJSON($vars){

		$data = new \stdClass();
		$data->labels = $vars['labels'];
		$data->datasets = array(
			(object) ['data' => $vars['data'], 'backgroundColor' => $this->chart_colors]
			// (object) ['data' => array($tM, $tF), 'backgroundColor' => $this->chart_colors]
		);
		return json_encode($data);
	}

	public function generateFile($file, $content) {
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/studentsanalytics/flatfiles/{$file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $content);
		fclose($myfile);
	}
}