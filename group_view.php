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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');


require_login();
// Check if plugin is enabled.
if (get_config('local_assignaddons', 'disableplugin')) {
    print_error('disable_assign_plugin', 'local_assignaddons');
}

$id = optional_param('id', 0, PARAM_INT);

$groupid = optional_param('groupid', 0, PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

$pageparams = array('id' => $id, 'assign' => $assign, 'groupid' => $groupid);

// The tool is only available after login in course since it is only available to teachers.
require_login($course);

$context = context_module::instance($cm->id);
$PAGE->set_title(get_string('coursetitle', 'moodle', array('course' => $course->fullname)));
$pagename = get_string('display_group_view', 'local_assignaddons');


$assign = new assign($context, $cm, $course);
$assign->get_instance();
$assign->set_context($context);


$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/assign/css/assign.css');
$PAGE->requires->js_call_amd('local_assignaddons/assign', 'display_results');

// The tool is only available after login in course since it is only available to teachers.
require_capability('moodle/course:manageactivities', $context);

$PAGE->navbar->add($course->shortname, new moodle_url($CFG->wwwroot.'/course/view.php?id='.$course->id));
$PAGE->navbar->add($assign->get_instance()->name, new moodle_url('/mod/assign/view.php', array('id' => $id)));

$link = new moodle_url($CFG->wwwroot .'/local/assign/group_view.php', $pageparams);
$PAGE->navbar->add(get_string('display_group_view', 'local_assignaddons'), $link);

if ($groupid) {
    $groupmembers = groups_get_members($groupid);
    $list[$assign->get_instance()->id] = array('cmid' => $id, 'name' => $assign->get_instance()->name,
            'duedate' => $assign->get_instance()->duedate, 'fromdate' => $assign->get_instance()->allowsubmissionsfromdate);
    foreach ($groupmembers as $member){
        $memberid = $member->id;
    }
    get_docs_for_student ( $memberid , $list, 'zip');
}
// Output.
$out = '';
print $OUTPUT->header();
echo $OUTPUT->box_start();
print $OUTPUT->heading(get_string( 'display_group_view', 'local_assignaddons' ));


// Get all groups.
$groups = array();
foreach (groups_get_all_groups($course->id) as $elt) {
    $groups[$elt->id] = $elt->name;
}
natsort($groups);

$groupname = '';
// Display all groups.
if ($groups) {
    $out .= '<ul>';
    //natsort($groups);
    foreach ($groups as $idgroup=>$name) {
        // Il s'agit d'une contrainte qui avait été mise pour les cours d'Arnaud Vervoort mais qui pose pb pour Jean-Yves Matroule
        //if (substr_count($name, 'Groupe')) {
            $out .= html_writer::start_tag('li', array('class' => 'memberlist', 'id' => $idgroup));
            if ($idgroup == $groupid) {
                $groupname = $name;
            }
            $link = '?id='.$id.'&groupid='.$idgroup.'#' . $idgroup;
            $out .= html_writer::link($link,  $name);
            $groupmembers = groups_get_members($idgroup);
           
            if (count($groupmembers)) {
                 $files = '<ul>';
                $out .= html_writer::start_tag('ol', array('class' => 'groupmembers hidden', 'id' => 'groupmembers'.$idgroup));
                foreach ($groupmembers as $member) {
                    $link2 = $link .'&userid='.$member->id;
                    $out .= html_writer::tag('li', $member->firstname . ' ' . $member->lastname);
                }
                $out .= html_writer::end_tag('ol');
                $submission = $assign->get_group_submission($member->id, 0, false);
                if ($submission) {
                    $submissiongroup = $assign->get_submission_group($member->id);
                    foreach ($assign->get_submission_plugins() as $plugin) {
                        if ($plugin->is_enabled() && $plugin->is_visible()) {
                            $pluginfiles = $plugin->get_files($submission, $member);
                            foreach ($pluginfiles as $zipfilename => $file) {
                                $files .= '<li> '   . $file->get_source() . ' - ' . $file->get_author() . '</li>';
                            }
                        }
                    }
                }
                $files .= '</ul>';
                $out .= $files;

            }
            $out .= html_writer::end_tag('li');
       // }
    }
    $out .= '</ul>';
}


echo $out;

echo $OUTPUT->box_end();

print $OUTPUT->footer();
