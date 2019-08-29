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

defined('MOODLE_INTERNAL') || die();


function local_assignaddons_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case    FEATURE_MOD_INTRO :
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/*
 * This function adds the course navigation menu to add a link to this tool.
 * @param object $parentnode
 * @param object $course
 * @param object $context
 * @global $CFG
 */
function local_assignaddons_extend_navigation_course(navigation_node $parentnode, stdClass $course , context_course $context  ) {
     global $CFG;
     // Show only if assign tool is activated.
    if (assign_is_used_in_course($course->id)  && has_capability('moodle/course:manageactivities', $context)) {
        $link = new moodle_url($CFG->wwwroot .'/local/assignaddons/index.php', array('id' => $course->id));
        $parentnode->add(get_string('assigns', 'local_assignaddons'), $link, navigation_node::TYPE_SETTING);
    }
}


/*
 * This function is usefull to extend settings navigation in an assignment
 * @param object $settingsnav
 * @global $PAGE
 */
function local_assignaddons_extend_settings_navigation($settingsnav) {
    global $PAGE, $COURSE;

    // Only add this settings item on non-site course pages.
    if (!$PAGE->course or $PAGE->course->id == 1) {
        return;
    }
    if ($settingnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
        if ($PAGE->cm->modname == 'assign') {
            $assignid = $PAGE->cm->instance;
            $groupmode = assign_is_in_team($assignid);
            if ($groupmode) {
                $url = new moodle_url('/local/assignaddons/group_view.php', array('id' =>$PAGE->cm->id));                
                $settingnode->add(get_string('display_group_view','local_assignaddons'), $url, settings_navigation::TYPE_SETTING);
            }
            $list = explode(',', get_config('local_assignaddons', 'courselistwithfillinsubmissionslink'));
            foreach ($list as $key=>$value) {
                $list[$key] = trim($value);
            }
            $displaylink = in_array($COURSE->shortname, $list);
            
            if ($displaylink) {
                // A link is added to fill in all missing submissions.                
                $url = new moodle_url('/local/assignaddons/fillin.php', array('cmid' => $PAGE->cm->id));
                $settingnode->add(get_string('fillinallmissingsubmissions','local_assignaddons'), $url, settings_navigation::TYPE_SETTING);
            }
        }
    }
}

/*
 * This function tells if an assignment is for a team (not individual)
 * @param int $assignid
 * @global $DB
 * @return boolean
 */
function assign_is_in_team($assignid) {
     global $DB;
     $info = $DB->get_record_select('assign', "id='$assignid'", null, 'teamsubmission');
     return $info->teamsubmission;
}

/*
 * This function tells if the module "assign" is used into a given course
 * @param int $courseid
 * @global $DB
 * @return boolean
 */
function assign_is_used_in_course($courseid) {
    global $DB;
    $moduleid = get_assign_id();
    $courseinfo = $DB->get_records('course_modules', array('course' => $courseid, 'module' => $moduleid), 'id');
    return (!empty($courseinfo));
}

/*
 * This function gives the id of a given module
 * @param /
 * @global $DB
 * @return int  id of the module if exists, false otherwise
 */
function get_assign_id() {
    global $DB;
    $mods = $DB->get_records('modules');
    foreach ($mods as $module) {
        if ($module->name == 'assign') {
            return $module->id;
        }
    }
    return false;
}

/*
 * Give the list of assigns for this course in array form
 * the key of the array is the id of the assign
 * each element of the array consists of some infos of this assign :
 *  - cmid : id of the instance corresponding to this assign in course_modules
 *  - name : title of the assign
 *  - dudate : Due date for the assign
 *  - fromdate : The value of allowsubmissionfromdate for this assign
 * @param int $courseid
 * @global $DB
 * @return array assignmentlist
 */
function get_assignments_list( $courseid ) {
    global $DB;
    $assignmentlist = array();

    // Get the semainarlist for this course.
    $list = $DB->get_records_select('assign', "course='$courseid'", null, 'duedate,allowsubmissionsfromdate');

    if (empty($list)) {
        return $assignmentlist;
    }

    // Get the id correspondign to the assign tool.
    $moduleid = get_assign_id ();

    foreach ($list as $value) {
        // Foreach assign of the list get the corresponding instance in the course_modules table.
        $info = $DB->get_record_select('course_modules',
                "course='$courseid' AND module='$moduleid' AND instance='".$value->id."'", null, 'id');
        // Construct the array with the corresponding value of this assign.
        if ($info) {
            $assignmentlist[$value->id] = array('cmid' => $info->id, 'name' => $value->name,
            'duedate' => $value->duedate, 'fromdate' => $value->allowsubmissionsfromdate);
        }
    }
    return $assignmentlist;
}
/*
 * Get the info for a given user according to the assignmentlist.
 * @param int userid
 * @param array assignmentlist
 * @global $DB
 * @return array assignmentinfouser : array
 */
function get_assignment_info_for_user ($userid, $assignmentlist) {
    global $DB;
    $assignmentinfoforuser = array();
    foreach (array_keys($assignmentlist) as $assignmentid) {
        // Foreach assign of the list get the infos concerning submisisons for the given user.
        $list = $DB->get_records('assign_submission', array('userid' => $userid,
            'assignment' => $assignmentid, 'status' => 'submitted'));
        if (!empty($list)) {
            foreach ($list as $info) {
                $assignmentinfoforuser[$assignmentid][] = array('id' => $info->id, 'timecreated' => $info->timecreated,
                    'timemodified' => $info->timemodified);
            }
        }
    }
    return $assignmentinfoforuser;
}

/*
 * Get the info for a given user according to a given assignment
 * @param int userid
 * @param array assignmentlist
 * @global $DB
 * @return array assignmentinfouser : array
 */
function get_assignment_info_for_user_and_assign ($userid, $assignmentid) {
    global $DB;
    $assignmentinfoforuser = array();
    $list = $DB->get_records('assign_submission', array('userid' => $userid,
        'assignment' => $assignmentid, 'status' => 'submitted'));
    if (!empty($list)) {
        foreach ($list as $info) {
            $assignmentinfoforuser[] = array('id' => $info->id, 'timecreated' => $info->timecreated,
                'timemodified' => $info->timemodified);
        }
    }
    return $assignmentinfoforuser;
}

/*
 * Clean some html tags
 * @param content : string
 * @return output : string
 */
function clean_html ($content) {
    $output = strip_tags($content);
    return $output;
}

/*
 * Get all documents of a given user for all the assigns of this course
 * @param user : object
 * @param seminarlist : array
 * @param format : string
 * @global $course
 * @global $CFG
 * @return a fiel is proposed to download
 */
function get_docs_for_student ( $user , $assignmentlist, $format) {
    global $course, $CFG;
    $filesforzipping = array();
    list($filename, $filesforzipping, $text) = send_content_for_user($user, $course, $assignmentlist, $filesforzipping, $format);
    if ($format != 'text') {
        // If format is zip, from the collected fields generate the zip archive.
        if (count($filesforzipping) != 0) {
            $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');
            // Zip files.
            $zipper = new zip_packer();
            if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
                // Send the zip file to download.
                send_temp_file($tempzip, $filename);
            }
        }
    } else {
        // If format is txt, send the onlinetext collected in a filename.
        send_content_in_file ($text, $filename);
    }
    return true;
}

/*
 * Get the online content for a submission
 * @param submissionid : int
 * @global $DB
 * @return string
 */
function get_onlinetext_for_submission ($submissionid) {
    global $DB;
    $list = $DB->get_record_select('assignsubmission_onlinetext', "submission='$submissionid'", null, 'onlinetext');
    if (!empty($list)) {
        foreach ($list as $value) {
            return $value;
        }
    }
    return '';
}

/*
 * This function gets all documents for a given user and assignmentlist in a specific format
 * @param array $userlist
 * @param array $assignmentlist
 * @param string $format (optional)
 * @global $course
 * @global $CFG
 * @global $DB
 */
function get_docs_for_all_students ( $userlist , $assignmentlist, $format = '' ) {
     global $course, $CFG, $DB;
     /*
      *  @todo : A directory shoud be build for each student with its submissions.
      */

    $filesforzipping = array();
    foreach ($userlist as $user) {
        $userid = $user['userid'];
        $student = $DB->get_record('user', array('id' => $userid));
        // Get the identity of the user according to its id.
        list($filename, $filesforzipping) = send_content_for_user($student, $course, $assignmentlist, $filesforzipping, $format, true);
    }
    // Send the zip file to download.

    if (count($filesforzipping) != 0) {
        $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');
        // Zip files.
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            // Send the zip file to download.
            send_temp_file($tempzip, $filename);
        }
    }
}

/*
 * Find correct filename for export
 * @param string $multi
 * @param object $course
 * @param object $user
 * @param string $format
 * @return string $filename
 */
function get_filename_for_export($multi, $course, $user, $format) {
    if (!$multi) {
        // The filename is based on the lastname and firstname of the user.
        $filename = clean_filename($course->shortname) . '_' . clean_filename($user->lastname . '_' .$user->firstname);
    } else {
        // Filename is the shortname of the course.
         $filename = clean_filename($course->shortname);
    }

    if ($format != 'text') {
        $filename .= '.zip';
    } else {
        $filename .= '.txt';
    }
    return $filename;
}

/*
 * Get all the contents (files or online contents) sent by a user for all the assignments
 * @param object $user
 * @param object $course
 * @param array $assignmentlist
 * @param object $filesforzipping
 * @param string $format
 * @param int $multi
 * @return array($filename, $filesforzipping, $text)
 */
function send_content_for_user($user, $course, $assignmentlist, $filesforzipping, $format = '', $multi = 0) {
    $filename = get_filename_for_export($multi, $course, $user, $format);
    $userid = $user->id;
    $i = 0;
    $text = '';
    foreach ($assignmentlist as $assignment) {
        $i++;
        // Foreach seminarlist get courseinfo and instance info of this assign.
        list ($course, $cm) = get_course_and_cm_from_cmid($assignment['cmid'], 'assign');
        // Get the context instance of this instance.
        $context = context_module::instance($cm->id);
        // Construct the assign.
        $assign = new assign($context, $cm, $course);

        $groupname = '';
        if ($assign->get_instance()->teamsubmission) {
            $submission = $assign->get_group_submission($userid, 0, false);
            $submissiongroup = $assign->get_submission_group($userid);
            if ($submissiongroup) {
                $groupname = $submissiongroup->name . '';
            } else {
                $groupname = get_string('defaultteam', 'assign');
            }
            if ($format != 'text') {
                $filename = $groupname . '.zip';
            } else {
                $filename = str_replace(' ', '_', $groupname) . '.txt';
            }
        } else {
            // Submission is individual, no group.
            $submission = $assign->get_user_submission($userid, false);
        }

        if ($assign->is_blind_marking()) {
            $prefix = str_replace('_', ' ', $groupname . get_string('participant', 'assign'));
            $prefix = clean_filename($prefix . '_' . $assign->get_uniqueid_for_user($userid) . '_');
        } else {
            if (!$assign->get_instance()->teamsubmission) {
                 $prefix = str_replace('_', ' ', $groupname . $user->lastname);
            } else {
                $prefix = str_replace('_', ' ', $groupname);
            }
            $prefix = clean_filename($prefix . '_' . $assign->get_uniqueid_for_user($userid) . '_');
        }
        if ($submission) {
            // If there is a submission, contruct the return according to the format (zip/txt).
            if ($format != 'text') {
                // If format is zip, then get all the files related to the submissions of this user.
                foreach ($assign->get_submission_plugins() as $plugin) {
                    if ($plugin->is_enabled() && $plugin->is_visible()) {
                        $pluginfiles = $plugin->get_files($submission, $user);
                        foreach ($pluginfiles as $zipfilename => $file) {
                            $subtype = $plugin->get_subtype();
                            $type = $plugin->get_type();
                            $prefixedfilename = clean_filename($prefix .
                                                               $subtype .
                                                               '_' .
                                                               $type .
                                                               '_' .
                                                               $zipfilename);
                            $filesforzipping[$prefixedfilename] = $file;
                        }
                    }
                }
            } else {
                // If format is text, get all the onlinetext for the submissions of this user.
                $mydate = $assignment['fromdate'];
                if ( !$mydate ) {
                    $mydate = $assignment['duedate'];
                }
                $text .= get_string('pluginname', 'assign') . ' '. $i . ' : '. $assignment['name'] . ' du '
                        . utf8_decode(userdate($mydate)). "\n \n";
                $content = get_onlinetext_for_submission ($submission->id);
                if ($content != '') {
                    $text .= clean_html($content) . "\n\n";
                } else {
                    $text .= ':  Pas de descriptif ' . "\r\n" . "\r\n";
                }
                $text .= '-----------------------------------------------------' . "\n";
            }
        }
    }
    return array($filename, $filesforzipping, $text);
}

/*
 * This function sends content in a given file
 * @param string $content
 * @patam string $filename
 */
function send_content_in_file ($content, $filename) {
    header("Content-type: application/force-download");
    header("Content-Disposition: attachment; filename=$filename");
    echo $content;
    exit;
}
