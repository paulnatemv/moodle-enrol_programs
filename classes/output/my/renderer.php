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

namespace enrol_programs\output\my;

use enrol_programs\local\allocation;
use enrol_programs\local\program;
use enrol_programs\local\util;
use enrol_programs\local\content\item,
    enrol_programs\local\content\top,
    enrol_programs\local\content\set,
    enrol_programs\local\content\course,
    enrol_programs\local\content\training;
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

    /**
     * Render the complete my program page with beautiful template.
     */
    public function render_my_program_page(\stdClass $program, \stdClass $source, \stdClass $allocation): string {
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
        $description = \file_rewrite_pluginfile_urls($program->description, 'pluginfile.php', $context->id, 'enrol_programs', 'description', $program->id);
        $description = \format_text($description, $program->descriptionformat, ['context' => $context]);

        // Get tags
        $taglist = [];
        if ($CFG->usetags) {
            $tags = \core_tag_tag::get_item_tags('enrol_programs', 'program', $program->id);
            foreach ($tags as $tag) {
                $taglist[] = ['name' => $tag->get_display_name()];
            }
        }

        // Get courses with real completion data
        $courses = $this->get_my_program_courses_data($program, $allocation);
        $coursecount = count($courses);
        $completedcount = 0;
        foreach ($courses as $course) {
            if ($course['iscompleted']) {
                $completedcount++;
            }
        }

        // Calculate overall progress
        $overallpercent = $coursecount > 0 ? round(($completedcount / $coursecount) * 100) : 0;

        // Get status HTML
        $statushtml = allocation::get_completion_status_html($program, $allocation);
        $statusclass = '';
        if (isset($allocation->timecompleted)) {
            $statusclass = 'status-completed';
        } else if ($completedcount > 0) {
            $statusclass = 'status-inprogress';
        }

        // Get source info
        $sourceclasses = allocation::get_source_classes();
        /** @var \enrol_programs\local\source\base $sourceclass */
        $sourceclass = $sourceclasses[$source->type];
        $sourceinfo = $sourceclass::render_allocation_source($program, $source, $allocation);

        // Prepare template data
        $templatedata = [
            'programname' => \format_string($program->fullname),
            'description' => $description,
            'imageurl' => $imageurl,
            'coursecount' => $coursecount,
            'completedcount' => $completedcount,
            'overallprogress' => $coursecount > 0,
            'overallpercent' => $overallpercent,
            'tags' => !empty($taglist),
            'taglist' => $taglist,
            'myprogramsurl' => (new moodle_url('/enrol/programs/my/index.php'))->out(false),
            'statushtml' => $statushtml,
            'statusclass' => $statusclass,
            'sourceinfo' => $sourceinfo,
            'allocationdate' => userdate($allocation->timeallocated),
            'programstart' => userdate($allocation->timestart),
            'programdue' => isset($allocation->timedue) ? userdate($allocation->timedue) : $strnotset,
            'programend' => isset($allocation->timeend) ? userdate($allocation->timeend) : $strnotset,
            'timecompleted' => isset($allocation->timecompleted) ? userdate($allocation->timecompleted) : '',
            'courses' => $courses,
        ];

        return $OUTPUT->render_from_template('enrol_programs/my_program_detail', $templatedata);
    }

    /**
     * Get program courses with real completion data for the current user.
     */
    protected function get_my_program_courses_data(\stdClass $program, \stdClass $allocation): array {
        global $DB;

        $top = program::load_content($program->id);
        $courses = [];
        $index = 1;

        $collectCourses = function(item $item) use (&$collectCourses, &$courses, &$index, $DB, $allocation): void {
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

                // Get real completion status from database
                $completion = $DB->get_record('enrol_programs_completions', [
                    'itemid' => $item->get_id(),
                    'allocationid' => $allocation->id
                ]);

                $iscompleted = false;
                $isinprogress = false;
                $isnotstarted = true;
                $progresspercent = 0;
                $completionclass = 'notstarted';
                $completiondate = '';

                if ($completion) {
                    // Course is completed
                    $iscompleted = true;
                    $isinprogress = false;
                    $isnotstarted = false;
                    $completionclass = 'completed';
                    $completiondate = userdate($completion->timecompleted, get_string('strftimedatetimeshort'));
                } else {
                    // Check if user has started the course (has any activity completion)
                    if ($coursecontext) {
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
                    'iscompleted' => $iscompleted,
                    'isinprogress' => $isinprogress,
                    'isnotstarted' => $isnotstarted,
                    'progresspercent' => $progresspercent,
                    'completionclass' => $completionclass,
                    'completiondate' => $completiondate,
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

    public function render_program(\stdClass $program): string {
        global $CFG;

        $context = \context::instance_by_id($program->contextid);
        $fullname = \format_string($program->fullname);
        $programicon = $this->output->pix_icon('program', '', 'enrol_programs');

        $description = \file_rewrite_pluginfile_urls($program->description, 'pluginfile.php', $context->id, 'enrol_programs', 'description', $program->id);
        $description = \format_text($description, $program->descriptionformat, ['context' => $context]);

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

        return $result;
    }

    public function render_user_allocation(stdClass $program, stdClass $source, stdClass $allocation): string {
        global $PAGE;
        $strnotset = get_string('notset', 'enrol_programs');

        $sourceclasses = allocation::get_source_classes();
        /** @var \enrol_programs\local\source\base $sourceclass */
        $sourceclass = $sourceclasses[$source->type];

        $result = '';

        $result .= '<dl class="row">';
        $result .= '<dt class="col-3">' . get_string('programstatus', 'enrol_programs') . ':</dt><dd class="col-9">'
            . allocation::get_completion_status_html($program, $allocation) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('source', 'enrol_programs') . ':</dt><dd class="col-9">'
            . $sourceclass::render_allocation_source($program, $source, $allocation) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('allocationdate', 'enrol_programs') . ':</dt><dd class="col-9">'
            . userdate($allocation->timeallocated) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('programstart', 'enrol_programs') . ':</dt><dd class="col-9">'
            . userdate($allocation->timestart) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('programdue', 'enrol_programs') . ':</dt><dd class="col-9">'
            . (isset($allocation->timedue) ? userdate($allocation->timedue) : $strnotset) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('programend', 'enrol_programs') . ':</dt><dd class="col-9">'
            . (isset($allocation->timeend) ? userdate($allocation->timeend) : $strnotset) . '</dd>';
        $result .= '<dt class="col-3">' . get_string('completiondate', 'enrol_programs') . ':</dt><dd class="col-9">'
            . (isset($allocation->timecompleted) ? userdate($allocation->timecompleted) : $strnotset) . '</dd>';

        $customfieldoutput = $PAGE->get_renderer('enrol_programs', 'customfield');
        $result .= $customfieldoutput->render_customfields($program->id);
        $result .= '</dl>';
        return $result;
    }

    public function render_user_progress(stdClass $program, stdClass $allocation): string {
        global $DB;

        $top = program::load_content($program->id);

        $rows = [];
        $renderercolumns = function(item $item, $itemdepth) use (&$renderercolumns, &$rows, $allocation, &$DB): void {
            $fullname = $item->get_fullname();
            $id = $item->get_id();
            $padding = str_repeat('&nbsp;', $itemdepth * 6);

            if ($item instanceof set) {
                $completiontype = $item->get_sequencetype_info();
            } else if ($item instanceof training) {
                $completiontype = $item->get_training_progress($allocation);
            } else {
                $completiontype = '';
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
            } else if ($item instanceof training) {
                $itemname = $padding . $this->output->pix_icon('itemtraining', get_string('training', 'enrol_programs'), 'enrol_programs') . $fullname;
            } else {
                $itemname = $padding . $this->output->pix_icon('itemset', get_string('set', 'enrol_programs'), 'enrol_programs') . $fullname;
            }

            if ($item instanceof top) {
                $points = '';
            } else {
                $points = $item->get_points();
            }

            $completioninfo = '';
            $completion = $DB->get_record('enrol_programs_completions', ['itemid' => $item->get_id(), 'allocationid' => $allocation->id]);
            if ($completion) {
                $completioninfo = userdate($completion->timecompleted, get_string('strftimedatetimeshort'));
            }

            $row = [$itemname, $points, $completiontype, $completioninfo];

            $rows[] = $row;

            foreach ($item->get_children() as $child) {
                $renderercolumns($child, $itemdepth + 1);
            }
        };
        $renderercolumns($top, 0);

        $table = new \html_table();
        $table->head = [
            get_string('item', 'enrol_programs'),
            get_string('itempoints', 'enrol_programs'),
            get_string('sequencetype', 'enrol_programs'),
            get_string('completiondate', 'enrol_programs'),
        ];
        $table->id = 'program_content';
        $table->attributes['class'] = 'admintable generaltable';
        $table->data = $rows;

        $result = $this->output->heading(get_string('tabcontent', 'enrol_programs'), 3);
        $result .= \html_writer::table($table);

        return $result;
    }

    /**
     * Returns body of My programs block.
     *
     * @return string
     */
    public function render_block_content(): string {
        global $DB;

        $allocations = allocation::get_my_allocations();
        if (!$allocations) {
            return '<em>' . get_string('errornomyprograms', 'enrol_programs') . '</em>';
        }

        $programicon = $this->output->pix_icon('program', '', 'enrol_programs');
        $strnotset = get_string('notset', 'enrol_programs');
        $dateformat = get_string('strftimedatetimeshort');

        foreach ($allocations as $allocation) {
            $row = [];

            $program = $DB->get_record('enrol_programs_programs', ['id' => $allocation->programid]);
            $fullname = $programicon . format_string($program->fullname);
            $detailurl = new moodle_url('/enrol/programs/catalogue/program.php', ['id' => $program->id]);
            $fullname = \html_writer::link($detailurl, $fullname);
            $row[] = $fullname;

            $row[] = \enrol_programs\local\allocation::get_completion_status_html($program, $allocation);

            $row[] = userdate($allocation->timestart, $dateformat);

            $row[] = (isset($allocation->timedue) ? userdate($allocation->timedue, $dateformat) : $strnotset);

            $row[] = (isset($allocation->timeend) ? userdate($allocation->timeend, $dateformat) : $strnotset);

            $data[] = $row;
        }

        $table = new \html_table();
        $table->head = [get_string('programname', 'enrol_programs'), get_string('programstatus', 'enrol_programs'),
            get_string('programstart', 'enrol_programs'), get_string('programdue', 'enrol_programs'),
            get_string('programend', 'enrol_programs')];
        $table->attributes['class'] = 'admintable generaltable';
        $table->data = $data;
        return \html_writer::table($table);
    }

    /**
     * Returns footer of My programs block.
     *
     * @return string
     */
    public function render_block_footer(): string {
        $url = \enrol_programs\local\catalogue::get_catalogue_url();
        if ($url) {
            return '<div class="float-end">' . \html_writer::link($url, get_string('catalogue', 'enrol_programs')) . '</div>';
        }
        return '';
    }

    /**
     * Render the beautiful my programs listing page.
     *
     * @param array $programs Array of program records
     * @return string
     */
    public function render_my_programs_page(array $programs): string {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;

        // Add Alpine.js for interactivity
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

        $dateformat = get_string('strftimedateshort');
        $programsdata = [];
        $completedprograms = 0;
        $inprogressprograms = 0;

        foreach ($programs as $program) {
            $allocation = $DB->get_record('enrol_programs_allocations', ['programid' => $program->id, 'userid' => $USER->id]);
            if (!$allocation) {
                continue;
            }

            $context = \context::instance_by_id($program->contextid);

            // Get image URL
            $imageurl = '';
            $presentation = (array)json_decode($program->presentationjson);
            if (!empty($presentation['image'])) {
                $imageurl = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                    '/' . $context->id . '/enrol_programs/image/' . $program->id . '/'. $presentation['image'], false);
            }

            // Get tags
            $taglist = [];
            if ($CFG->usetags) {
                $tags = \core_tag_tag::get_item_tags('enrol_programs', 'program', $program->id);
                foreach ($tags as $tag) {
                    $taglist[] = ['name' => $tag->get_display_name()];
                }
            }

            // Get course completion data
            $courses = $this->get_my_program_courses_data($program, $allocation);
            $totalcourses = count($courses);
            $completedcourses = 0;
            foreach ($courses as $course) {
                if ($course['iscompleted']) {
                    $completedcourses++;
                }
            }
            $progresspercent = $totalcourses > 0 ? round(($completedcourses / $totalcourses) * 100) : 0;

            // Determine completion status
            $iscompleted = isset($allocation->timecompleted);
            $isinprogress = !$iscompleted && $completedcourses > 0;
            $isnotstarted = !$iscompleted && $completedcourses === 0;

            if ($iscompleted) {
                $completionclass = 'completed';
                $completedprograms++;
            } else if ($isinprogress) {
                $completionclass = 'inprogress';
                $inprogressprograms++;
            } else {
                $completionclass = 'notstarted';
                $inprogressprograms++; // Count not started as in progress for stats
            }

            // Check if overdue
            $hasdue = isset($allocation->timedue) && $allocation->timedue > 0;
            $isoverdue = $hasdue && !$iscompleted && $allocation->timedue < time();

            $programsdata[] = [
                'id' => $program->id,
                'fullname' => \format_string($program->fullname),
                'imageurl' => $imageurl,
                'detailurl' => (new moodle_url('/enrol/programs/my/program.php', ['id' => $program->id]))->out(false),
                'tags' => !empty($taglist),
                'taglist' => $taglist,
                'totalcourses' => $totalcourses,
                'completedcourses' => $completedcourses,
                'progresspercent' => $progresspercent,
                'iscompleted' => $iscompleted,
                'isinprogress' => $isinprogress,
                'isnotstarted' => $isnotstarted,
                'completionclass' => $completionclass,
                'statusclass' => $completionclass,
                'startdate' => userdate($allocation->timestart, $dateformat),
                'hasdue' => $hasdue,
                'duedate' => $hasdue ? userdate($allocation->timedue, $dateformat) : '',
                'isoverdue' => $isoverdue,
            ];
        }

        // Get catalogue URL
        $catalogueurl = \enrol_programs\local\catalogue::get_catalogue_url();

        $templatedata = [
            'programs' => $programsdata,
            'hasstats' => count($programsdata) > 0,
            'totalprograms' => count($programsdata),
            'completedprograms' => $completedprograms,
            'inprogressprograms' => $inprogressprograms,
            'hascatalogue' => !empty($catalogueurl),
            'catalogueurl' => $catalogueurl ? $catalogueurl->out(false) : '',
        ];

        return $OUTPUT->render_from_template('enrol_programs/my_programs', $templatedata);
    }
}
