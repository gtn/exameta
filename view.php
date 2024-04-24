<?php
/**
 * This page prints a particular instance of exameta
 *
 * @author
 * @version $Id: view.php,v 1.6 2007/09/03 12:23:36 jamiesensei Exp $
 * @package exameta
 **/

global $COURSE, $CFG, $DB, $USER, $PAGE;

require_once("inc.php");
require_once($CFG->dirroot . '/blocks/exacomp/lib/lib.php');
require_once($CFG->dirroot . '/blocks/exacomp/renderer.php');


$id = optional_param('id', 0, PARAM_INT);
//$id = optional_param_array('id', PARAM_INT); // TODO: add other params.. for now I commented it out as it throws an error if you only use a single value
// from moodle 2.2 on we have to use optional_param_array, optional_param won't accept arrays
$out = array();
$img_files = array();

$responses = function_exists('optional_param_array') ? optional_param_array('responses', array(), PARAM_TEXT) : optional_param('responses', array(), PARAM_RAW);
if (!$cm = $DB->get_record("course_modules", array("id" => $id))) {
    print_error("Course Module ID was incorrect");
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("Course is misconfigured");
}

require_login($course->id);

/// Print the page header
$navlinks = array();
$navlinks[] = array('courseid' => $course->id, 'link' => "index.php?id=$course->course", 'type' => 'activity');

//$navigation = build_navigation($navlinks);
$partUrl = explode("/", $_SERVER['PHP_SELF'], 2);
$pos = strpos($partUrl[1], "/");
$url = new moodle_url(substr($partUrl[1], $pos), array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_heading(get_string("modulename", "exameta"));

$output = block_exacomp_get_renderer();
$output->requires()->js('/blocks/exacomp/javascript/jquery.inputmask.bundle.js', true);
$output->requires()->js('/blocks/exacomp/javascript/competence_tree_common.js', true);
$output->requires()->css('/blocks/exacomp/css/competence_tree_common.css');
$PAGE->requires->js('/blocks/exacomp/javascript/fullcalendar/moment.min.js', true);
$PAGE->requires->js('/blocks/exacomp/javascript/jquery.daterangepicker.min.js', true);
$PAGE->requires->css('/blocks/exacomp/css/daterangepicker.min.css', true);

echo $output->header();

$context = context_module::instance($cm->id);
$course_settings = block_exacomp_get_settings_by_course($course->id);

/// Print the main part of the page
$html_tables = [];
$results = exameta_get_competence_ids($course->id);

foreach ($results as $result) {
    $ret = block_exacomp_init_overview_data($course->id, $result->subjid, $result->topicid, $result->nivid, false,
        false, false, false, @$course_settings->hideglobalsubjects);

    if (!$ret) {
        print_error('not configured');
    }
    $competence_tree = block_exacomp_get_competence_tree($course->id,
        $result->subjid,
        $result->topicid,
        false,
        $result->id,
        true,
        $course_settings->filteredtaxonomies,
        true,
        false,
        false,
        false,
        false,
        false,
        null,
        false);

    // TODO: print column information for print

    // loop through all pages (eg. when all students should be printed)
    if ($students) {
        for ($group_i = 0; $group_i < count($students); $group_i += BLOCK_EXACOMP_STUDENTS_PER_COLUMN) {
            $students_to_print = array_slice($students, $group_i, BLOCK_EXACOMP_STUDENTS_PER_COLUMN, true);
            $html_header = $output->overview_metadata($result->title, $result->topicid, null, $result->id);

            $competence_overview = $output->competence_overview($competence_tree,
                $course->id,
                $students_to_print,
                false,
                $isTeacher ? BLOCK_EXACOMP_ROLE_TEACHER : BLOCK_EXACOMP_ROLE_STUDENT,
                $scheme,
                $result->id != BLOCK_EXACOMP_SHOW_ALL_NIVEAUS,
                0,
                false);

            $html_tables[] = $competence_overview;
            block_exacomp\printer::competence_overview($result->subjid, $result->topicid, $result->id, null, $html_header, $html_tables);
        }
    }
    echo '<div class="clearfix"></div>';
    echo html_writer::start_tag("div", array("id" => "exabis_competences_block"));
    echo html_writer::start_tag("div", array("class" => "exabis_competencies_lis"));
    echo html_writer::start_tag("div", array("class" => "gridlayout"));

    $competence_overview = $output->competence_overview($competence_tree,
        $course->id,
        $students,
        true,
        $isTeacher ? BLOCK_EXACOMP_ROLE_TEACHER : BLOCK_EXACOMP_ROLE_STUDENT,
        $scheme,
        ($selectedNiveau->id != BLOCK_EXACOMP_SHOW_ALL_NIVEAUS),
        0,
        $isEditingTeacher);

    echo '<div class="clearfix"></div>';

    echo $competence_overview;
    echo '</div>';
    echo html_writer::end_tag("div");
    echo html_writer::end_tag("div");
    echo html_writer::end_tag("div");
}

/// Finish the page
echo $output->footer();
?>
