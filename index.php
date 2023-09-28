<?php // $Id: index.php,v 1.7 2007/09/03 12:23:36 jamiesensei Exp $
// todo check db
/**
 * This page lists all the instances of exameta in a particular course
 *
 * @author
 * @version $Id: index.php,v 1.7 2007/09/03 12:23:36 jamiesensei Exp $
 * @package exametas
 **/

$id = required_param('id', PARAM_INT);   // course
$PAGE->set_url('/mod/exameta/index.php', array('id'=>$id));
if (! $course = $DB->get_record("course", array("id"=>$id))) {
	print_error("Course ID is incorrect");
}

require_login($course->id);

/// Get all required stringsexametas

$strexametas = get_string("modulenameplural", "exameta");
$strexameta  = get_string("modulename", "exameta");


/// Print the header

$navlinks = array();
$navlinks[] = array('name' => $strexametas, 'link' => '', 'type' => 'activity');
//$navigation = build_navigation($navlinks);
$PAGE->navbar->add($course->fullname, new moodle_url('', array('id' => $course->id)));
//print_header_simple("$strexametass", "", $navigation, "", "", true, "", navmenu($course));

/// Get all the appropriate data

if (! $exametas = get_all_instances_in_course("exametas", $course)) {
	notice("There are no exametas", "../../course/view.php?id=$course->id");
	die;
}

/// Print the list of instances (your module will probably extend this)

$timenow = time();
$strname  = get_string("name");
$strweek  = get_string("week");
$strtopic  = get_string("topic");

$table = new html_table();

if ($course->format == "weeks") {
	$table->head  = array ($strweek, $strname);
	$table->align = array ("center", "left");
} else if ($course->format == "topics") {
	$table->head  = array ($strtopic, $strname);
	$table->align = array ("center", "left", "left", "left");
} else {
	$table->head  = array ($strname);
	$table->align = array ("left", "left", "left");
}

foreach ($exametas as $exameta) {
	if (!$exameta->visible) {
		//Show dimmed if the mod is hidden
		$link = "<a class=\"dimmed\" href=\"view.php?id=$exameta->coursemodule\">$exameta->name</a>";
	} else {
		//Show normal if the mod is visible
		$link = "<a href=\"view.php?id=$exameta->coursemodule\">$exameta->name</a>";
	}

	if ($course->format == "weeks" or $course->format == "topics") {
		$table->data[] = array ($exameta->section, $link);
	} else {
		$table->data[] = array ($link);
	}
}

echo "<br />";

// Display the table.
echo html_writer::table($table);

// Finish the page
echo $OUTPUT->footer();
