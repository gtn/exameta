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
function exameta_add_instance($meta) {
    global $DB;

    $test = new stdClass();
    $test->courseid = $meta->course;
    $test->name = $meta->name;
    $test->intro = $meta->intro;
    $test->topicid = $meta->topicid ?? 0; // there is no topicid select in the mod_form?!?

    // if ($meta->course == null && $meta->course > 1) { // TODO: what should this check? it will never be true since if the course is null it cannot be larger than 1
    //     print_error(get_string("courseError", "exameta"));
    // } else if (!$DB->get_record("block", ["name" => "exacomp"])) {
    //     print_error(get_string("compNotInstalled", "exameta"));
    // } else if (!$DB->get_records("block_exacompexampvisibility", ["courseid" => $meta->course])) {
    //     print_error(get_string("notVisible", "exameta"));
    // }

    if (!$meta->id = $DB->insert_record("exameta", $test)) {
        return false;
    }

    return $meta->id;
}

function exameta_update_instance($exameta) {
    // code copied from mod_label
    global $DB;

    $exameta->timemodified = time();
    $exameta->id = $exameta->instance;

    $completiontimeexpected = !empty($exameta->completionexpected) ? $exameta->completionexpected : null;
    // \core_completion\api::update_completion_date_event($exameta->coursemodule, 'exameta', $exameta->id, $completiontimeexpected);

    return $DB->update_record("exameta", $exameta);
}

function exameta_delete_instance($id) {
    // code copied from mod_label
    global $DB;

    if (! $exameta = $DB->get_record("exameta", array("id"=>$id))) {
        return false;
    }

    $result = true;

    $cm = get_coursemodule_from_instance('exameta', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'exameta', $exameta->id, null);

    if (! $DB->delete_records("exameta", array("id"=>$exameta->id))) {
        $result = false;
    }

    return $result;
}

function exameta_cm_info_dynamic(cm_info $cm) {
    // keine Überschrift usw. anzeigen
    $cm->set_no_view_link();
}

function exameta_cm_info_view(cm_info $cm) {
    if (method_exists(\block_edupublisher\api::class, 'get_course_summary')) {
        $output = \block_edupublisher\api::get_course_summary($cm->get_course()->id);
        // } else {
        // Loading the competencies from exacomp is not working anymore!
        //     $competence_overview = exameta_build_table($cm->get_course()->id);
    } else {
        $output = 'keine Metadaten';
    }

    // hack: bei aktivitäten ohne header wird ein padding-right gesetzt, dieses entfernen
    $output .= '<style>
        .activity.activity-wrapper.exameta .contentwithoutlink {
            padding-right: 0;
        }
    </style>';

    $cm->set_content($output);
}

/*
function exameta_print_tabs($meta, $currenttab) {
    global $CFG, $USER, $DB, $cm;

    $tabs = array();
    $row = array();
    $inactive = array();
    $activated = array();

    $row[] = new tabobject('show', $CFG->wwwroot . '/mod/exameta/view.php?id=' . $meta->course, 'Startseite');
    $context = context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = $CFG->wwwroot . '/course/mod.php?update=' . $cm->id . '&return=1&sesskey=' . sesskey();
        $row[] = new tabobject('edit', $url, 'Edit');
    }
    $tabs[] = $row;
    print_tabs($tabs, $currenttab, $inactive, $activated);
}

function exameta_get_competence_ids(int $courseid): array {
    global $DB;

    // hier wird get_recordsset() verwendet, da moodle sonst "duplicate ids" error wirft
    $result = iterator_to_array($DB->get_recordset_sql('
        SELECT topic.id as topicid, topic.title, topic.subjid, niv.id as niveauid
        FROM {block_exacomptopicvisibility} as vs
        inner join {block_exacomptopics} as topic on vs.topicid = topic.id
        inner join {block_exacompsubjects} as sub on topic.subjid = sub.id
        inner join {block_exacompniveaus} as niv on niv.source = sub.source
        WHERE vs.courseid=?
    ', [$courseid]));

    return $result;
}

function exameta_build_table(int $courseid) {
    global $CFG, $DB, $PAGE, $USER;

    require_once($CFG->dirroot . '/blocks/exacomp/lib/lib.php');

    $scheme = block_exacomp_get_grading_scheme($courseid);
    $isEditingTeacher = block_exacomp_is_editingteacher($courseid, $USER->id);
    $isTeacher = block_exacomp_is_teacher();
    $metaModule = $DB->get_record("modules", array('name' => 'exameta'));
    $moduleId = $metaModule->id;
    if (!$isTeacher) {
        $editmode = 0;
    } else {
        $editmode = 1;
    }

    // if (!$cm = $DB->get_record("course_modules", ['course' => $courseid, 'module' => $moduleId])) {
    //     print_error("Exameta is currently not installed in this course!");
    // }

    if ($isTeacher) {
        //if ($slicestudentlist) {
        //    $limitfrom = $slicestartposition + 1; // sql from
        //    $limitnum = BLOCK_EXACOMP_STUDENTS_PER_COLUMN;
        //} else {
        $limitfrom = '';
        $limitnum = '';
        //}
        $students = $allCourseStudents = block_exacomp_get_students_by_course($courseid, $limitfrom, $limitnum);
    } else {
        $students = $allCourseStudents = array($USER->id => $USER);
    }

    $output = $PAGE->get_renderer('block_exacomp');

    // $context = context_module::instance($cm->id);
    $course_settings = block_exacomp_get_settings_by_course($courseid);

    /// Print the main part of the page
    $html_tables = [];
    $results = exameta_get_competence_ids($courseid);
    $competence_overview = "";
    foreach ($results as $result) {
        $ret = block_exacomp_init_overview_data($courseid, $result->subjid, $result->topicid, $result->niveauid, $editmode,
            $isTeacher, ($isTeacher ? 0 : $USER->id), ($isTeacher) ? false : true, @$course_settings->hideglobalsubjects);

        if (!$ret) {
            print_error('not configured');
        }
        list($courseSubjects, $courseTopics, $niveaus, $selectedSubject, $selectedTopic, $selectedNiveau) = $ret;
        $competence_tree = block_exacomp_get_competence_tree($courseid,
            $result->subjid,
            $result->topicid,
            false,
            $result->niveauid,
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
            $html_header = $output->overview_metadata($result->title, $result->topicid, null, $result->niveauid);

            $competence_overview .= $output->competence_overview($competence_tree,
                $courseid,
                $students_to_print,
                $showevaluation,
                $isTeacher ? BLOCK_EXACOMP_ROLE_TEACHER : BLOCK_EXACOMP_ROLE_STUDENT,
                $scheme,
                $result->id != BLOCK_EXACOMP_SHOW_ALL_NIVEAUS,
                0,
                $isEditingTeacher);

            $html_tables[] = $competence_overview;

            // this does not work, because it prints a pdf?!?
            // block_exacomp\printer::competence_overview($result->subjid, $result->topicid, $result->id, null, $html_header,
            //     $html_tables);
        }

        $competence_overview .= '<div class="clearfix"></div>';
        $competence_overview .= html_writer::start_tag("div", array("id" => "exabis_competences_block"));
        $competence_overview .= html_writer::start_tag("div", array("class" => "exabis_competencies_lis"));
        $competence_overview .= html_writer::start_tag("div", array("class" => "gridlayout"));

        $competence_overview .= $output->competence_overview($competence_tree,
            $courseid,
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
        $competence_overview .= '<br/>';
    }

    return $competence_overview;
}
*/
