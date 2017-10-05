<?php
namespace report_studentsanalytics\task;

require_once($CFG->libdir . '/gradelib.php');

class StudentCareerAnalytics extends \core\task\scheduled_task
{

	// domDocument object
	public $domDoc;

	// <table> opening tag
	public $tblOpen = '<table class="table table-condensed table-striped table-hover">';

	// the Generated html
	public $html = '';

	// active class counter on tab
	public $i = 0;

	public function get_name()
	{
		// Shown in admin screens
		return get_string('pluginname', 'report_studentsanalytics');
	}

	public function execute()
	{
		if (!isset($DB)) {
			global $DB;
		}

		$this->DB = $DB;

		$dm = $this->dataMining();
		$rg = $this->dataVisualizationAllGrades($dm);
		$this->generateFile('studentAnalytic_gradesPerCohort.php', $this->html);

		// reset the HTML output
		$this->html = '';
		$this->dataVisualizationFinalTest();
		$this->generateFile('studentAnalytic_FinalPerCohort.php', $this->html);
	}

	public function getCohorts()
	{
		$Cohorts = $this->DB->get_records_sql(
			'SELECT id, name, idnumber FROM mdl_cohort WHERE visible = 1 ORDER BY idnumber ASC;', null, $limitfrom=0, $limitnum=0
		);

		return $Cohorts;
	}

	public function getCourses()
	{
		$courses = $this->DB->get_records_sql("SELECT id, fullname FROM mdl_course WHERE format = 'topics' OR format = 'singleactivity' ORDER BY sortorder", null, $limitfrom=0, $limitnum=0);
		return $courses;
	}

	public function getQuizByCourse($courseid)
	{
		$quizs = $this->DB->get_records_sql("SELECT id, name FROM mdl_quiz WHERE course = :courseid", array('courseid'=>$courseid), $limitfrom=0, $limitnum=0);
		return $quizs;
	}

	public function getStudentPerCohort($cohortid) {

		$isCohort = $this->DB->record_exists('cohort', array('id'=>$cohortid, 'visible'=>1));
		if ($isCohort) {
			$Cohorts = $this->DB->get_records_sql(
				'SELECT id, cohortid, userid
				FROM mdl_cohort_members
				WHERE cohortid = :cohortid;', array('cohortid'=>$cohortid), $limitfrom=0, $limitnum=0
			);
		} else {
			$Cohorts = false;
		}

		return $Cohorts;
	}

	public function getAvgPercentGrades($Grades)
	{
		$gradeCount = $i = 0;

		foreach ($Grades->items[0]->grades as $key => $grade) {

			if (!is_null($grade->grade)) {

				$gradeNumber = (int)$grade->grade;

				if ($gradeNumber == 0) {
					$pGrade = 0;
				} else {
					$pGrade = ($grade->grade / $Grades->items[0]->grademax) * 100;
				}

				$gradeCount += $pGrade;

				$i++;
			}
		}

		$avg = round(($gradeCount / $i));

		return $avg;
	}

	private function makeJSON($vars)
	{

		$data = new \stdClass();
		$data->labels = $vars['labels'];
		$data->datasets = $vars['datasets'];

		if (isset($vars['options'])) {
			$data->options = $vars['options'];
		}

		return json_encode($data);
	}

	public function dataVisualizationFinalTest()
	{

		$colors    = array('#ff6384', '#36a2eb', '#4bc0c0');

		$allGrades = array();

		$tabPanels = array();

		$CohortsTab = $Cohorts = $this->getCohorts();

		// add the combined graph tab
		$tab = new \stdClass();
		$tab->name = "Combined";
		$tab->idnumber = 'CH000A';
		$CohortsTab[] = $tab;

		// Generate Cohorts tab name
		$this->html = $this->dataVizCohortTabNames($CohortsTab);
		unset($CohortsTab);

		foreach ($Cohorts as $key => $cohort) {
			$c = ($key == 1) ? ' active' : '';
			$tabPanels[] = '<div role="tabpanel" class="tab-pane'.$c.'" id="'.$cohort->idnumber.'"><canvas id="chart-'.$cohort->idnumber.'"></div>';

			// get student per cohort
			$Students = $this->getStudentPerCohort($cohort->id);

			// get all courses
			$Courses = $this->getCourses();

			foreach ($Courses as $course) {

				// get all quiz for this course
				$Quiz = $this->getQuizByCourse($course->id);
				foreach ($Quiz as $quiz) {
					$grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, array_keys($Students));

					$labels[] = $grading_info->items[0]->name;
					$d[] = $this->getAvgPercentGrades($grading_info);
				}
			}

			$data[] = (object) ['data' => $d];

			// Generate the graph data file for the current cohort
			$this->generateFile(
				'studentAnalytic_'.$cohort->idnumber.'.json',
				$this->makeJSON(array('labels'=>$labels, 'datasets'=> $data))
			);

			// keep a copy of all grades for the combined graph
			$allGrades['labels'] = $labels;
			$dataAll[] = (object) [
				'backgroundColor' => $colors[$key-1],
				'data' => $d,
				'label' => $cohort->name,
				'stack' => 'stak'.$cohort->idnumber
			];

			// reset the graph per cohort data
			unset($labels, $data, $d);
		}

		// Generate the graph data file for the combined cohort
		$this->generateFile(
			'studentAnalytic_CH000A.json',
			$this->makeJSON(array('labels'=>$allGrades['labels'], 'datasets'=>$dataAll))
		);

		// add the combined tab-pane
		$tabPanels[] = '<div role="tabpanel" class="tab-pane" id="CH000A"><canvas id="chart-CH000A"></div>';

		// Add all the tab-panel into the tab-content
		$this->html .= '<div class="tab-content">';
		$this->html .= implode('', $tabPanels);
		$this->html .= '</div>';
	}

	public function dataVisualizationAllGrades($data)
	{
		// render cohort tabs
		$this->html .= $this->dataVizCohortTabNames($data);

		// Cohort tab content
		$this->html .= '<div class="tab-content">';

		$i=0;
		foreach ($data as $k => $v) {
			if ($i == 0) {
				$c=' active';
				$i=$i+1;
			} else {
				$c='';
			}

			$this->html .= '<div class="tab-pane'.$c.'" id="'.$v->idnumber.'">'."\n";
				$this->html .= $this->dataVizFieldTabNames($v);

				$this->html .= '<div class="tab-content">';

					foreach ($v->fields as $fieldK => $fieldV) {
						$this->html .= '<div class="tab-pane'.$c.'" id="'.$v->idnumber.'_'.$fieldK.'">'."\n";
						// reset .active counter
						$c = '';

						foreach ($fieldV as $key => $uselessV) {
							foreach ($uselessV as $key => $value) {
								$this->html .= $this->tblOpen;
								$this->html .= '<tr><th>'.$value['name'].'</th><th>&nbsp;</th></tr>';
								foreach ($value['grades'] as $keyFG => $valueFG) {
									$this->html .= '<tr>';
									$this->html .= '<td class="col-md-8">'.$valueFG['name'].'</td>';
									$this->html .= '<td class="col-md-4">'.$valueFG['grade'].'</td>';
									$this->html .= '</tr>';
								}
								$this->html .= '</table>'."\n";
							}
						}

						$this->html .= '</div>'; // .tab-pane
					}

				$this->html .= '</div>'; // .tab-content
			$this->html .= '</div>'; // .tab-pane
		}

		$this->html .= '</div>'; // .tab-content
	}

	public function dataVizFieldTabNames($data)
	{
		$html = '<ul class="nav nav-tabs" role="tablist">'."\n";
		foreach ($data->fields as $key => $value) {
			if ($this->i == 0) {
				$c = ' class="active"';
			} else {
				$c = '';
			}
			$html .= '<li role="presentation"'.$c.'><a href="#'.$data->idnumber.'_'.$key.'" aria-controls="'.$data->idnumber.'_'.$key.'" role="tab" data-toggle="tab">'.$key.'</a></li>';
			$this->i = $this->i + 1;
		}
		$html .= '</ul>'."\n";
		return $html;
	}

	public function dataVizCohortTabNames($data)
	{
		$i = 0;
		$html = '<ul class="nav nav-tabs" role="tablist">'."\n";
		foreach ($data as $key => $value) {
			if ($i == 0) {
				$c = ' class="active"';
			} else {
				$c = '';
			}
			$html .= '<li role="presentation"'.$c.'><a href="#'.$value->idnumber.'" aria-controls="'.$value->idnumber.'" role="tab" data-toggle="tab">'.$value->name.'</a></li>';
			$i++;
		}
		$html .= '</ul>'."\n";

		return $html;
	}

	public function dataMining()
	{
		$Cohorts = $this->getCohorts();

		foreach ($Cohorts as $key => $cohort) {
			$CustomFields = $this->DB->get_records_sql("SELECT id, shortname, name, categoryid, datatype FROM mdl_user_info_field WHERE categoryid = 2 ORDER BY shortname ASC;", null, $limitfrom=0, $limitnum=0);
			foreach ($CustomFields as $k => $field) {

				// the <select> custom fields
				if ($field->datatype == 'menu') {
					$Cohorts[$key]->fields[$field->shortname] = $this->reportTypeMenu($field, $cohort);

				// Date custom fields
				} else if ($field->datatype == 'datetime') {
					$Cohorts[$key]->fields[$field->shortname] = $this->reportTypeDate($field, $cohort);
				}
			}
		}

		return $Cohorts;
	}

	public function reportTypeMenu($field, $cohort) {

		$fields = array();

		// Get all possible values for the passed field
		// ie: Purchasing, HR, Finance, etc
		$dataElements = $this->DB->get_records_sql(
			'SELECT id, data FROM mdl_user_info_data WHERE fieldid = '.$field->id.' AND data != "" GROUP BY data ORDER BY data DESC;', null, $limitfrom=0, $limitnum=0
		);

		foreach ($dataElements as $ek => $ev) {

			// Get the student per career fields
			$students = $this->DB->get_records_sql("
				SELECT mdl_user_info_data.userid
				FROM mdl_user_info_data
				LEFT JOIN mdl_cohort_members ON mdl_user_info_data.userid = mdl_cohort_members.userid
				WHERE
					mdl_user_info_data.fieldid = :fieldid AND
					mdl_user_info_data.data = :data AND
					mdl_cohort_members.cohortid = :cohortid",
				array('fieldid'=>$field->id, 'data'=>$ev->data, 'cohortid'=>$cohort->id), $limitfrom=0, $limitnum=0
			);

			// Get all the course
			//$courses = $this->DB->get_records_sql("SELECT id, fullname FROM mdl_course WHERE format = 'topics' OR format = 'singleactivity' ORDER BY sortorder", null, $limitfrom=0, $limitnum=0);
			$courses = $this->getCourses();

			// field value name
			// ie: Finance, HR, IT for department field
			$fields[$field->id][$ek]['name'] = $ev->data;

			foreach ($courses as $ck => $cv) {

				// Get all the quiz in a course
				$quizs = $this->DB->get_records_sql("SELECT id, name FROM mdl_quiz WHERE course = ?", array($cv->id), $limitfrom=0, $limitnum=0);
				foreach ($quizs as $qk => $qv) {

					$gradesPerQuiz = grade_get_grades($cv->id, 'mod', 'quiz', $qv->id, array_keys($students));

					// we dont use outcomes
					unset($gradesPerQuiz->outcomes);

					// cycle trhough quiz
					foreach ($gradesPerQuiz as $gk => $gv) {
						//////////////////////
						//// get average grade

						// if the current test has grade (if at least 1 student completed it)
						if (isset($gv[0]->grades)) {
							// $grades: the sum of all grades in a quiz
							// $studentInGrades: how many student has completed the quiz
							$grades = $studentInGrades = 0;

							// foreach grades
							foreach ($gv[0]->grades as $gsk => $gsv) {
								// if the setudent has a grade for this quiz
								if (!is_null($gsv->grade)) {
									$grades += (int)$gsv->str_grade;
									$studentInGrades = $studentInGrades + 1;
								}
							}
							if ($grades != 0) {
								// get grades average
								$gradeAvg = round($grades / $studentInGrades);
								$gradeAvg .= '%';
							} else {
								$gradeAvg = '-';
							}
						} else {
								$gradeAvg = '-';
						}

						if ($gradeAvg == '-') {
							$gradeOutput = '<span class="note">No grade yet</span>';
						} else {
							$student = $this->pluralizer($studentInGrades, 'student');
							$gradeOutput = $gradeAvg.' <span class="note">('.$studentInGrades.' '.$student.')</span>';
						}
						$fields[$field->id][$ek]['grades'][$qk]['name'] = $gv[0]->name;
						$fields[$field->id][$ek]['grades'][$qk]['grade'] = $gradeOutput;
					}
				}
			}
		}
		return $fields;
	}

	/**
	 * Generates the report for date custom field type
	 *
	 * @param obj $field     The custom field id
	 * @param obj $cohort    The cohort object
	 * @return
	 */
	public function reportTypeDate($field, $cohort)
	{
		$fields = array();

		$students = $this->DB->get_records_sql("
			SELECT a.userid, a.timefinish, a.sumgrades, c.cohortid, u.data as dob, DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '%Y') - DATE_FORMAT(FROM_UNIXTIME(u.data), '%Y') - (DATE_FORMAT(FROM_UNIXTIME(a.timefinish), '00-%m-%d') < DATE_FORMAT(FROM_UNIXTIME(u.data), '00-%m-%d')) AS joinDate
			FROM (mdl_quiz_attempts AS a)
				JOIN moodle.mdl_user_info_data AS u ON a.userid = u.userid
				JOIN mdl_cohort_members AS c ON a.userid = c.userid
			WHERE a.quiz = 12 AND a.state = 'finished' AND u.fieldid = :fieldid AND c.cohortid = :cohortid
			GROUP BY a.userid
			ORDER BY joinDate ASC;",
			array('fieldid'=>$field->id, 'cohortid'=>$cohort->id), $limitfrom=0, $limitnum=0
		);

		// num of student who received a grade for final test
		$ageCount = count($students);

		// Create the age group (20, 25, 30, 35, etc)
		$ageRange = range(0, 50, 1);

		$ageGroups = array();

		foreach ($students as $k => $v) {
			foreach ($ageRange as $ak => $av) {
				// break at last age range (can't do +1 on last element)
				if (count($ageRange) == $ak){break;}
				// next age group key
				$nav = $ak + 1;
				if ($v->joindate >= $av && $v->joindate < $ageRange[$nav]) {

					if (!isset($ageGroups[$av.'-'.$ageRange[$nav]])) {
						$ageGroups[$av.'-'.$ageRange[$nav]]['nb_student'] = (int) 0;
						$ageGroups[$av.'-'.$ageRange[$nav]]['grades'] = (int) 0;
					}

					$ageGroups[$av.'-'.$ageRange[$nav]]['nb_student']++;
					$ageGroups[$av.'-'.$ageRange[$nav]]['grades'] = (int) $v->sumgrades * 10;

					// once the student is placed in an age group, break the loop;
					break;
				}
			}
		}

		$fields[$field->id]['0']['name'] = $this->parseBiName($field->name);

		foreach ($ageGroups as $k => $v) {
			$perc = round($v['grades'] / $v['nb_student']);
			$student = $this->pluralizer($v['nb_student'], 'student');

			// trick the pluralizer, make 0-1 = 1 and the rest to 2 to get the plural form
			$num = ($k == '0-1') ? 1 : 2;
			$year = $this->pluralizer($num, 'year');
			$fields[$field->id]['0']['grades'][$k]['name']  = $k.' '.$year;
			$fields[$field->id]['0']['grades'][$k]['grade'] = $perc.'% <span class="note">('.$v['nb_student'].' '.$student.')</span>';
		}

		return $fields;
	}

	/**
	 * Retrieve the english part of a multi-lang string
	 * ie: <span class="multilang" lang="en">Join Date</span><span class="multilang" lang="id">bergabung</span>
	 *
	 * @param String $xmlstr The mlang XML string
	 * @return String English term
	 */
	public function parseBiName($xmlstr)
	{
		if (!empty($xmlstr)) {
			if (!is_object($this->domDoc)) {
				$this->domDoc = new \domDocument('1.0', 'utf-8');
				$this->domDoc->preserveWhiteSpace = false;
			}
			$this->domDoc->loadHTML($xmlstr);
			$span = $this->domDoc->getElementsByTagName('span');
			$str = $span->item(0)->nodeValue;
		} else {
			$str = '';
		}

		// Garbage
		$span = '';

		return $str;
	}

	/**
	 * Return the plural form of $str
	 *
	 * @param int $num Number
	 * @param str $str Word to pluralize
	 * @return String English term
	 */
	public function pluralizer($num, $str)
	{
		if (is_numeric($num) || !empty($str)) {
			if ($num > 1) {
				$str .= 's';
			}
		} else {
			$str = '';
		}

		return $str;
	}

	public function generateFile($file, $content) {
		global $CFG;
		$myfile = fopen($CFG->dirroot."/report/studentsanalytics/flatfiles/{$file}", "w+") or die("Unable to open file!");
		fwrite($myfile, $content);
		fclose($myfile);
	}
}