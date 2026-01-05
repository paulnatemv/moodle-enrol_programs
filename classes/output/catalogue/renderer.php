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

namespace enrol_programs\output\catalogue;

use enrol_programs\local\allocation;
use enrol_programs\local\program;
use enrol_programs\local\util;
use enrol_programs\local\content\item,
    enrol_programs\local\content\top,
    enrol_programs\local\content\set,
    enrol_programs\local\content\course;
use stdClass, moodle_url, tabobject;

/**
 * Program catalogue renderer.
 *
 * @package    enrol_programs
 * @copyright  2022 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    public function render_program(\stdClass $program): string {
        global $CFG, $DB, $PAGE, $OUTPUT;

        // Add Alpine.js for tab switching
        $PAGE->requires->js_amd_inline("
            require([], function() {
                if (!window.Alpine) {
                    var script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
                    script.defer = true;
                    document.head.appendChild(script);
                }
            });
        ");

        $strnotset = get_string('notset', 'enrol_programs');
        $context = \context::instance_by_id($program->contextid);

        // Get image URL
        $imageurl = '';
        $presentation = (array)json_decode($program->presentationjson);
        if (!empty($presentation['image'])) {
            $imageurl = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                '/' . $context->id . '/enrol_programs/image/' . $program->id . '/'. $presentation['image'], false);
        }

        // Get description
        $description = file_rewrite_pluginfile_urls($program->description, 'pluginfile.php', $context->id, 'enrol_programs', 'description', $program->id);
        $description = format_text($description, $program->descriptionformat, ['context' => $context]);
        $descriptiontext = strip_tags($description);

        // Get tags
        $taglist = [];
        if ($CFG->usetags) {
            $tags = \core_tag_tag::get_item_tags('enrol_programs', 'program', $program->id);
            foreach ($tags as $tag) {
                $taglist[] = ['name' => $tag->get_display_name()];
            }
        }

        // Get course count
        $coursecount = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {enrol_programs_items} WHERE programid = ? AND courseid IS NOT NULL",
            [$program->id]
        );

        // Check prerequisites
        $hasrequirements = (stripos($descriptiontext, 'prerequisite') !== false) ||
                          (stripos($descriptiontext, 'requirement') !== false);

        // Get actions
        $actions = [];
        $sourceclasses = allocation::get_source_classes();
        foreach ($sourceclasses as $type => $classname) {
            $source = $DB->get_record('enrol_programs_sources', ['programid' => $program->id, 'type' => $type]);
            if (!$source) {
                continue;
            }
            $actions = array_merge($actions, $classname::get_catalogue_actions($program, $source));
        }

        // Get courses with completion status
        $courses = $this->get_program_courses_data($program);

        // Prepare template data
        $templatedata = [
            'programname' => format_string($program->fullname),
            'description' => $description,
            'imageurl' => $imageurl,
            'coursecount' => $coursecount,
            'hasrequirements' => $hasrequirements,
            'tags' => !empty($taglist),
            'taglist' => $taglist,
            'catalogueurl' => (new moodle_url('/enrol/programs/catalogue/index.php'))->out(false),
            'status' => '<span class="badge badge-secondary">' . get_string('errornoallocation', 'enrol_programs') . '</span>',
            'allocationstart' => isset($program->timeallocationstart) ? userdate($program->timeallocationstart) : $strnotset,
            'allocationend' => isset($program->timeallocationend) ? userdate($program->timeallocationend) : $strnotset,
            'actions' => !empty($actions) ? implode(' ', $actions) : '',
            'courses' => $courses,
        ];

        return $OUTPUT->render_from_template('enrol_programs/program_detail', $templatedata);
    }

    /**
     * Get program courses with real completion data for template.
     */
    protected function get_program_courses_data(\stdClass $program): array {
        global $DB, $CFG, $USER;

        $top = program::load_content($program->id);
        $courses = [];
        $index = 1;

        // Get user's allocation for this program (if any)
        $allocation = null;
        if (isloggedin() && !isguestuser()) {
            $allocation = $DB->get_record('enrol_programs_allocations', [
                'programid' => $program->id,
                'userid' => $USER->id,
                'archived' => 0
            ]);
        }

        $collectCourses = function(item $item) use (&$collectCourses, &$courses, &$index, $DB, $CFG, $allocation): void {
            if ($item instanceof course) {
                $courseid = $item->get_courseid();
                $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);

                $courseurl = '';
                $coursename = $item->get_fullname();

                if ($coursecontext) {
                    $canaccesscourse = false;
                    if (has_capability('moodle/course:view', $coursecontext)) {
                        $canaccesscourse = true;
                    } else {
                        $courserecord = get_course($courseid);
                        if ($courserecord && can_access_course($courserecord, null, '', true)) {
                            $canaccesscourse = true;
                        }
                    }
                    if ($canaccesscourse) {
                        $courseurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
                    }
                }

                // Get real completion status
                $iscompleted = false;
                $isinprogress = false;
                $isnotstarted = true;
                $progresspercent = 0;
                $completionclass = 'notstarted';

                if ($allocation) {
                    // Check program completion table
                    $completion = $DB->get_record('enrol_programs_completions', [
                        'itemid' => $item->get_id(),
                        'allocationid' => $allocation->id
                    ]);

                    if ($completion) {
                        // Course is completed in program
                        $iscompleted = true;
                        $isinprogress = false;
                        $isnotstarted = false;
                        $completionclass = 'completed';
                        $progresspercent = 100;
                    } else if ($coursecontext) {
                        // Check Moodle course completion progress
                        $courserecord = get_course($courseid);
                        if ($courserecord) {
                            $completioninfo = new \completion_info($courserecord);
                            if ($completioninfo->is_enabled()) {
                                $progress = \core_completion\progress::get_course_progress_percentage($courserecord, $allocation->userid);
                                if ($progress !== null && $progress > 0) {
                                    $iscompleted = false;
                                    $isinprogress = true;
                                    $isnotstarted = false;
                                    $progresspercent = round($progress);
                                    $completionclass = 'inprogress';
                                }
                            }
                        }
                    }
                }

                $courses[] = [
                    'index' => $index,
                    'courseid' => $courseid,
                    'coursename' => $coursename,
                    'courseurl' => $courseurl,
                    'coursesummary' => '',
                    'iscompleted' => $iscompleted,
                    'isinprogress' => $isinprogress,
                    'isnotstarted' => $isnotstarted,
                    'progresspercent' => $progresspercent,
                    'completionclass' => $completionclass,
                ];

                $index++;
            }

            foreach ($item->get_children() as $child) {
                $collectCourses($child);
            }
        };

        $collectCourses($top);

        return $courses;
    }

    public function render_program_original(\stdClass $program): string {
        global $CFG, $DB, $PAGE;

        $strnotset = get_string('notset', 'enrol_programs');

        $context = \context::instance_by_id($program->contextid);
        $fullname = format_string($program->fullname);
        $programicon = $this->output->pix_icon('program', '', 'enrol_programs');

        $description = file_rewrite_pluginfile_urls($program->description, 'pluginfile.php', $context->id, 'enrol_programs', 'description', $program->id);
        $description = format_text($description, $program->descriptionformat, ['context' => $context]);

        $tagsdiv = '';
        if ($CFG->usetags) {
            $tags = \core_tag_tag::get_item_tags('enrol_programs', 'program', $program->id);
            if ($tags) {
                $tagsdiv = $this->output->tag_list($tags, '', 'program-tags');
            }
        }

        $programimage = '';
        $presentation = (array)json_decode($program->presentationjson);
        if (!empty($presentation['image'])) {
            $imageurl = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                '/' . $context->id . '/enrol_programs/image/' . $program->id . '/'. $presentation['image'], false);
            $programimage = '<div class="float-end programimage">' . \html_writer::img($imageurl, '') . '</div>';
        }

        $result = '';
        $result .= <<<EOT
<div class="programbox clearfix" data-programid="$program->id">
  $programimage
  <div class="info">
  <div class="info">
    <h2 class="programname">{$programicon}{$fullname}</h2>
  </div>$tagsdiv
  <div class="content">
    <div class="summary">$description</div>
  </div>
</div>
EOT;

        $result .= '<dl class="row">';
        $result .= '<dt class="col-3">' . get_string('programstatus', 'enrol_programs') . ':</dt><dd class="col-9">'
            . get_string('errornoallocation', 'enrol_programs') . '</dd>';
        $result .= '<dt class="col-3">' . get_string('allocationstart', 'enrol_programs') . ':</dt><dd class="col-9">'
            . (isset($program->timeallocationstart) ? userdate($program->timeallocationstart) : $strnotset) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('allocationend', 'enrol_programs') . ':</dt><dd class="col-9">'
            . (isset($program->timeallocationend) ? userdate($program->timeallocationend) : $strnotset) . '</dd>';
        $customfieldoutput = $PAGE->get_renderer('enrol_programs', 'customfield');
        $result .= $customfieldoutput->render_customfields($program->id);
        $result .= '</dl>';

        $actions = [];
        /** @var \enrol_programs\local\source\base[] $sourceclasses */ // Type hack.
        $sourceclasses = allocation::get_source_classes();
        foreach ($sourceclasses as $type => $classname) {
            $source = $DB->get_record('enrol_programs_sources', ['programid' => $program->id, 'type' => $type]);
            if (!$source) {
                continue;
            }
            $actions = array_merge($actions, $classname::get_catalogue_actions($program, $source));
        }

        if ($actions) {
            $result .= '<div class="buttons mb-5">';
            $result .= implode(' ', $actions);
            $result .= '</div>';
        }

        $result .= $this->output->heading(get_string('tabcontent', 'enrol_programs'), 3);

        $result .= $this->render_program_content($program);

        return $result;
    }

    public function render_program_content(stdClass $program): string {
        global $DB;

        $top = program::load_content($program->id);

        $rows = [];
        $renderercolumns = function(item $item, $itemdepth) use (&$renderercolumns, &$rows, &$DB): void {
            $fullname = $item->get_fullname();
            $id = $item->get_id();
            $padding = str_repeat('&nbsp;', $itemdepth * 6);

            $completiontype = '';
            if ($item instanceof set) {
                $completiontype = $item->get_sequencetype_info();
            }
            if ($completiondelay = $item->get_completiondelay()) {
                if ($completiontype !== '') {
                    $completiontype .= '<br />';
                }
                $completiontype .= '<small>' . get_string('completiondelay', 'enrol_programs') . ': ' . util::format_duration($completiondelay) . '</small>';
            }

            if ($item instanceof course) {
                $courseid = $item->get_courseid();
                $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
                if ($coursecontext) {
                    $canaccesscourse = false;
                    if (has_capability('moodle/course:view', $coursecontext)) {
                        $canaccesscourse = true;
                    } else {
                        $course = get_course($courseid);
                        if ($course && can_access_course($course, null, '', true)) {
                            $canaccesscourse = true;
                        }
                    }
                    if ($canaccesscourse) {
                        $detailurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
                        $fullname = \html_writer::link($detailurl, $fullname);
                    }
                } else {
                    $fullname .= ' <span class="badge badge-danger">' . get_string('errorcoursemissing', 'enrol_programs') . '</span>';
                }
            }

            if ($item instanceof top) {
                $itemname = $this->output->pix_icon('itemtop', get_string('program', 'enrol_programs'), 'enrol_programs') . '&nbsp;' . $fullname;
            } else if ($item instanceof course) {
                $itemname = $padding . $this->output->pix_icon('itemcourse', get_string('course'), 'enrol_programs') . $fullname;
            } else {
                $itemname = $padding . $this->output->pix_icon('itemset', get_string('set', 'enrol_programs'), 'enrol_programs') . $fullname;
            }

            $row = [$itemname, $completiontype];

            $rows[] = $row;

            foreach ($item->get_children() as $child) {
                $renderercolumns($child, $itemdepth + 1);
            }
        };
        $renderercolumns($top, 0);

        $table = new \html_table();
        $table->head = [get_string('item', 'enrol_programs'), get_string('sequencetype', 'enrol_programs')];
        $table->id = 'program_content';
        $table->attributes['class'] = 'admintable generaltable';
        $table->data = $rows;

        return \html_writer::table($table);
    }
}
