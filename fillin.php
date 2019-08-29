<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

 /* @package    local_assignaddons
 * @copyright  2018 Namur University
 * @autor       Laurence Dumortier <laurence.dumortier@unamur.be>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');


// Check if plugin is enabled.
if (get_config(' local_assignaddons', 'disableplugin')) {
    print_error('disable_assign_plugin', ' local_assignaddons');
}

$id = optional_param('cmid', 0, PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

$pageparams = array('id' => $id);

// The tool is only available after login in course since it is only available to teachers.
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_title(get_string('coursetitle', 'moodle', array('course' => $course->fullname)));
//$pagename = get_string('fillinallmissingsubmissions', ' local_assignaddons');


$assign = new assign($context, $cm, $course);
$assign->get_instance();
$assign->set_context($context);


// Print the page header.
$PAGE->set_url('/local/assign/fillin.php', array('cmid' => $cm->id));

$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_heading($course->fullname);

// The tool is only available after login in course since it is only available to teachers.
require_capability('moodle/course:manageactivities', $context);



// Output.
$out = '';
print $OUTPUT->header();
echo $OUTPUT->box_start();
print $OUTPUT->heading(get_string('fillinallmissingsubmissions',  'local_assignaddons' ));

// Get Student User List.

$students = array();
list ($select, $from, $where, $params) = user_get_participants_sql($course->id, 0, 0, 5);
$list = $DB->get_recordset_sql("$select $from $where", $params);
foreach ($list as $student) {
    $key = str_replace('', '_', $student->lastname) . '_' . str_replace('', '_', $student->firstname);
    if (!array_key_exists($key, $students)) {
        $students[$key] = array('lastname' => $student->lastname, 'firstname' => $student->firstname,
            'email' => $student->email, 'userid' => $student->id);
    }
}
ksort($students);


foreach ($students as $currentuser) {
    // Get semainarsubmissions for a given user.
    $assignmentinfoforuser = get_assignment_info_for_user_and_assign($currentuser['userid'], $assign->get_instance()->id);

    if (empty($assignmentinfoforuser)) {
        $out .= '<br> missing pour '  .  $currentuser['lastname'] . ' ' . $currentuser['firstname'] . $currentuser['userid']; 
        $data = new stdClass();
        $data->assignment = $assign->get_instance()->id;
        $data->userid = $currentuser['userid'];
        $data->timecreated = time();
        $data->timemodified = time();
        $data->status = 'submitted';
        $data->groupid= '';
        $data->latest = '1';
        $submissionid = $DB->insert_record('assign_submission', $data);
        $data = new stdClass();
        $data->assignment = $assign->get_instance()->id;
        $data->submission = $submissionid;
        $data->onlinetext = 'Travail soumis pas ' . $currentuser['firstname'] . ' ' .$currentuser['lastname'];
        $DB->insert_record('assignsubmission_onlinetext', $data);
    }
}

/*
 * $participants = $workshop->get_participants();

    // Get all participants with a submission related to this workshop.
    $alreadysubmittedparticipants = $workshop->get_participants(true);

    // Foreach participant, if he has not yet submit, submit a simulated work with the name of the workshop?
    foreach ($participants as $userid => $elt) {
        if (!array_key_exists($userid, $alreadysubmittedparticipants)) {
            $content .= '<br> on insère pour ' . $elt->lastname . ' ' . $elt->firstname . '  le travail de titre '. $workshop->name;
            $data = new stdClass();
            $data->workshopid = $workshop->id;
            $data->title = $workshop->name;
            $data->authorid = $userid;
            $data->timecreated = time();
            $data->timemodified = time();
            $DB->insert_record('workshop_submissions', $data);
        }
    }
 */

echo $out;

echo $OUTPUT->box_end();

print $OUTPUT->footer();
