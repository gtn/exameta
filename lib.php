<?php
/**
 * Given an object containing all the necessary data, 
 * (defined by the form in mod.html) this function 
 * will create a new instance and return the id number 
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted exagames record
 **/
function exameta_add_instance($meta)
{   
	global $DB;

    $test = new stdClass();
    $test->courseid = $meta->course;
    $test->name = $meta->name;
    $test->intro = $meta->intro;
    $test->topicid = $meta->topicid;

    if($meta->course == null && $meta->course > 1){
        print_error(get_string("courseError", "exameta"));
    } else if(! $DB->get_record("block", ["name"=>"exacomp"])){
        print_error(get_string("compNotInstalled", "exameta"));
    } else if (! $DB->get_records("block_exacompexampvisibility", ["courseid"=>$meta->course])){
        print_error(get_string("notVisible", "exameta"));
    } 

    if (!$meta->id = $DB->insert_record("exameta", $test)) {
        return false;
    }

	return $meta->id;
}

function exameta_update_instance($meta)
{
	global $DB;

    $tmp = $DB->get_record_sql("SELECT ex.id FROM mdl_exameta as ex inner join mdl_course_modules as mo on ex.courseid = mo.course WHERE mo.course = ". $meta->course);
	$meta->id = $tmp->id;
    $meta->intro = exameta_build_table($meta->topicid);

    if (!$DB->update_record("exameta", $meta)) {
        return false;  // some error occurred
    }

    return true;
}

function exameta_delete_instance($id) {
	global $DB;

    $tmp = $DB->get_record_sql("SELECT ex.id FROM mdl_exameta as ex inner join mdl_course_modules as mo on ex.courseid = mo.course WHERE ex.id = ". $id);
	$id = $tmp->id;
	
    if (! $meta = $DB->get_record("exameta", array("id"=>$id))) {
        return false;
    }

    $result = true;

    # Delete any dependent records here #

    if (! $DB->delete_records("exameta", array("id"=>$id))) {
        $result = false;
    }

	return $result;
}

function exameta_load_schooltypes(){
    global $DB;

    $inf = array();
    foreach($DB->get_records('block_exacompschooltypes') as $i){
        $inf[$i->id] = $i->title;
    }

    return $inf;
}

function exameta_load_subjects(){
    global $DB;

    $inf = array();
    foreach($DB->get_records('block_exacompsubjects') as $i){
        $inf[$i->id] = $i->title;
    }

    return $inf;
}

function exameta_load_topics(){
    global $DB;

    $inf = array();
    foreach($DB->get_records('block_exacomptopics') as $i){
        $inf[$i->id] = $i->title;
    }

    return $inf;
}

function exameta_print_tabs($meta, $currenttab)
{
	global $CFG, $USER, $DB, $cm;

	$tabs = array();
	$row  = array();
	$inactive = array();
	$activated = array();

    $row[] = new tabobject('show', $CFG->wwwroot.'/mod/exameta/view.php?id='.$meta->course, 'Startseite');
    $context = context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
		$url = $CFG->wwwroot.'/course/mod.php?update='.$cm->id.'&return=1&sesskey='.sesskey();
		$row[] = new tabobject('edit', $url, 'Edit');
	}
    $tabs[] = $row;
	print_tabs($tabs, $currenttab, $inactive, $activated);
}

function exameta_get_competence_ids($meta){
    global $COURSE, $DB, $PAGE;

    $result = $DB->get_record_sql('SELECT DISTINCT(topic.id) as topicid, topic.title, topic.subjid, niv.id as nivid FROM mdl_block_exacomptopicvisibility as vs
    inner join mdl_block_exacomptopics as topic on vs.topicid = topic.id
    inner join mdl_block_exacompsubjects as sub on topic.subjid = sub.id
    inner join mdl_block_exacompniveaus as niv on niv.source = sub.source
    WHERE vs.courseid = ' . $COURSE->id . ' AND topic.id = ' . $meta->topicid . ' LIMIT 1');

    return $result;
}

function exameta_cm_info_view(cm_info $cm) {
    global $DB;
    $cm->set_custom_cmlist_item(true);
    $info = $DB->get_record('exameta', ["id"=>$cm->instance]);
    $competence_overview = exameta_build_table($info);
    $info->intro = $competence_overview;

    $DB->update_record('exameta', $info);
    return $info;
 }
 function exameta_build_table($meta){
    global $COURSE, $DB, $PAGE, $USER;

    $scheme = block_exacomp_get_grading_scheme($COURSE->id);
    $isEditingTeacher = block_exacomp_is_editingteacher($COURSE->id, $USER->id);
    $isTeacher = block_exacomp_is_teacher();
    $metaModule = $DB->get_record("modules", array('name'=>'exameta'));
    $moduleId = $metaModule->id;
    $courseId = $COURSE->id;
    if (!$isTeacher) {
        $editmode = 0;
    } else {
        $editmode = 1;
    }

	if (! $cm = $DB->get_record("course_modules", ['course'=>$courseId, 'module'=>$moduleId])) {
		print_error("Exameta is currently not installed in this course!");
	}

    if ($isTeacher) {
        //if ($slicestudentlist) {
        //    $limitfrom = $slicestartposition + 1; // sql from
        //    $limitnum = BLOCK_EXACOMP_STUDENTS_PER_COLUMN;
        //} else {
        $limitfrom = '';
        $limitnum = '';
        //}
        $students = $allCourseStudents = block_exacomp_get_students_by_course($COURSE->id, $limitfrom, $limitnum);
    } else {
        $students = $allCourseStudents = array($USER->id => $USER);
    }

    $output = $PAGE->get_renderer('block_exacomp');

    $context = context_module::instance($cm->id);
    $course_settings = block_exacomp_get_settings_by_course($COURSE->id);

    /// Print the main part of the page
    $html_tables = [];
    $result = exameta_get_competence_ids($meta);

    $ret = block_exacomp_init_overview_data($COURSE->id, $result->subjid, $result->topicid, $result->nivid, $editmode, $isTeacher, ($isTeacher ? 0 : $USER->id), ($isTeacher) ? false : true, @$course_settings->hideglobalsubjects);

    if (!$ret) {
        print_error('not configured');
    }
    list($courseSubjects, $courseTopics, $niveaus, $selectedSubject, $selectedTopic, $selectedNiveau) = $ret;    
    $competence_tree = block_exacomp_get_competence_tree($COURSE->id,
    $result->subjid,
    $result->topicid,
    false,
    null,
    true,
    $course_settings->filteredtaxonomies,
    true,
    false,
    false,
    false,
    ($isTeacher) ? false : true,
    false,
    null,
    $editmode);
    // TODO: print column information for print

    // loop through all pages (eg. when all students should be printed)
    for ($group_i = 0; $group_i < count($students); $group_i += BLOCK_EXACOMP_STUDENTS_PER_COLUMN) {
            $students_to_print = array_slice($students, $group_i, BLOCK_EXACOMP_STUDENTS_PER_COLUMN, true);
            $html_header = $output->overview_metadata($result->title, $result->topicid, null, $result->nivid);

            $competence_overview = $output->competence_overview($competence_tree,
            $COURSE->id,
            $students_to_print,
            $showevaluation,
            $isTeacher ? BLOCK_EXACOMP_ROLE_TEACHER : BLOCK_EXACOMP_ROLE_STUDENT,
            $scheme,
            $result->id != BLOCK_EXACOMP_SHOW_ALL_NIVEAUS,
            0,
            $isEditingTeacher);

            $html_tables[] = $competence_overview;
                    block_exacomp\printer::competence_overview($result->subjid, $result->topicid, $result->id, null, $html_header, $html_tables);
            }

    $competence_overview = '<div class="clearfix"></div>';
    $competence_overview .= html_writer::start_tag("div", array("id" => "exabis_competences_block"));
    $competence_overview .= html_writer::start_tag("div", array("class" => "exabis_competencies_lis"));
    $competence_overview .= html_writer::start_tag("div", array("class" => "gridlayout"));

            $competence_overview = $output->competence_overview($competence_tree,
    $COURSE->id,
    $students,
    true,
    $isTeacher ? BLOCK_EXACOMP_ROLE_TEACHER : BLOCK_EXACOMP_ROLE_STUDENT,
    $scheme,
    ($selectedNiveau->id != BLOCK_EXACOMP_SHOW_ALL_NIVEAUS),
    0,
    $isEditingTeacher);

    $competence_overview .= '<div class="clearfix"></div>';

    $competence_overview .= '</div>';
    $competence_overview .= html_writer::end_tag("div");
    $competence_overview .= html_writer::end_tag("div");
    $competence_overview .= html_writer::end_tag("div");
    
    return $competence_overview;
 }