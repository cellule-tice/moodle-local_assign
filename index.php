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
if (get_config('local_assignaddons', 'disableplugin')) {
    print_error('disable_assign_plugin', 'local_assignaddons');
}

$id = optional_param('id', 0, PARAM_INT); // This is de course id.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

$pageparams = array('id' => $id);

// The tool is only available after login in course since it is only available to teachers.
require_login($course);

$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_title(get_string('coursetitle', 'moodle', array('course' => $course->fullname)));
$pagename = get_string('pluginname', 'local_assignaddons');
$PAGE->set_url(new moodle_url('/local/assignaddons/', $pageparams));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/assignaddons/css/assign.css');

// The tool is only available after login in course since it is only available to teachers.
require_capability('moodle/course:manageactivities', $context);

// Get all assigns list for this course.
$assignmentslist = get_assignments_list($id);

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

$cmd = optional_param('cmd', '', PARAM_ALPHANUM);
$format = optional_param('format', '', PARAM_ALPHANUM);
if ($cmd == 'downloadzip') {
    $userid = optional_param('userid', 0, PARAM_INT);
    $format = ($format == 'text') ? $format : '';
    $student = $DB->get_record('user', array('id' => $userid));
    get_docs_for_student ( $student , $assignmentslist, $format);
} else if ($cmd == 'downloadallzip') {
    set_time_limit(0);
    get_docs_for_all_students ($students , $assignmentslist, $format );
}

// Output.
$out = '';
print $OUTPUT->header();
echo $OUTPUT->box_start();
$downloadall = html_writer::link($_SERVER['PHP_SELF'].'?id='.$id.'&cmd=downloadallzip', $OUTPUT->pix_icon('t/download', ''));
print $OUTPUT->heading(get_string( 'assigns', 'local_assignaddons' ) . '&nbsp;' . $downloadall);

// Display table header.

$out .= get_string('seminarlist', 'local_assignaddons');
$items = array();
$i = 0;
foreach ($assignmentslist as $key => $assignment) {
    $i++;
    $items[] = get_string('pluginname', 'assign') . ' ' . $i . ' : '.   $assignment['name'];
}
$out .= html_writer:: alist($items);

$table = new html_table();
$table->attributes['class'] = 'assigntable generaltable';
$headers = array('N ', get_string('lastname'), get_string('firstname'));
$i = 0;
$nbsubmissions = array();
foreach ($assignmentslist as $key => $assignment) {
    $i++;
    $headers[] = 'S&eacute;m. ' . $i;
    $nbsubmissions[$key] = 0;
}
$headers[] = get_string('download') . ' compil';
$headers[] = get_string('download') . ' zip';
$table->head = $headers;

// Display users.

$i = 0;

foreach ($students as $currentuser) {
    $i++;
    $row = new html_table_row();
    $row->cells[] = $i;
    $row->cells[] = htmlspecialchars($currentuser['lastname']);
    $row->cells[] = htmlspecialchars($currentuser['firstname']);

    // Get semainarsubmissions for a given user.
    $assignmentinfoforuser = get_assignment_info_for_user($currentuser['userid'], $assignmentslist);

    foreach ($assignmentslist as $assignid => $assignment) {
        // Foreach assign display in color if the submission was done int ime (green), later (orange), not done (red).
        $color = '';
        if (!array_key_exists($assignid, $assignmentinfoforuser)) {
            $color = 'red';
            $statusseminar = 'notsubmitted';
        } else if ($assignmentinfoforuser[$assignid][0]['timecreated'] <= $assignment['duedate']) {
            $color = 'green';
            $statusseminar = 'submitted';
            $nbsubmissions[$assignid]++;
        } else {
            $color = 'coral';
            $statusseminar = 'latesubmitted';
            $nbsubmissions[$assignid]++;
        }
        $cell = new html_table_cell();
        $cell->text = '&nbsp;';
        $cell->attributes = array('class' => 'seminar ' . $statusseminar);

        $row->cells[] = $cell;
    }

    // Give links for download.
    $cell = new html_table_cell();
    $cell->text = html_writer::link($_SERVER['PHP_SELF'].'?id='.$id. '&cmd=downloadzip&userid='.$currentuser['userid']
            .'&format=text',
            $OUTPUT->pix_icon('t/download', ''));
    $cell->attributes = array('class' => 'text-sm-center');
    $row->cells[] = $cell;

    $cell = new html_table_cell();
    $cell->text = html_writer::link($_SERVER['PHP_SELF'].'?id='.$id.'&cmd=downloadzip&userid='.$currentuser['userid'].'&format=zip',
            $OUTPUT->pix_icon('t/download', ''));
    $cell->attributes = array('class' => 'text-sm-center');
    $row->cells[] = $cell;

    $table->data[] = $row;
} // END - foreach users

// Display table footer.

$row = new html_table_row();
$cell = new html_table_cell ();
$cell->colspan = 4;
$cell->text = get_string('nb_assignments', 'local_assignaddons');
$row->cells[] = $cell;
foreach ($nbsubmissions as $nb) {
    $row->cells[] = $nb;
}
$row->cells[] = '&nbsp;';

$table->data[] = $row;

echo $out;
echo html_writer::table($table);

echo $OUTPUT->box_end();

print $OUTPUT->footer();
