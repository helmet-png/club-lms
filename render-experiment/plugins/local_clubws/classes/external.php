<?php
namespace local_clubws;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * 批次建立 URL 活動：含「檢視即完成」打卡、日期解鎖，並自動補齊所需週次區塊。
 */
class external extends external_api {

    public static function create_urls_parameters() {
        return new external_function_parameters([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'      => new external_value(PARAM_INT, '課程 id'),
                    'section'       => new external_value(PARAM_INT, '週次區塊編號 (1..N)'),
                    'name'          => new external_value(PARAM_TEXT, '活動名稱'),
                    'url'           => new external_value(PARAM_RAW, '外部網址'),
                    'intro'         => new external_value(PARAM_RAW, '說明 HTML', VALUE_DEFAULT, ''),
                    'availablefrom' => new external_value(PARAM_INT, '解鎖時間 unix ts，0=不限制', VALUE_DEFAULT, 0),
                ])
            ),
        ]);
    }

    public static function create_urls($items) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $params = self::validate_parameters(self::create_urls_parameters(), ['items' => $items]);

        // 確保站台層級的完成度追蹤與存取限制有開，否則打卡/解鎖不會生效。
        set_config('enablecompletion', 1);
        set_config('enableavailability', 1);

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST);
        $results = [];

        foreach ($params['items'] as $item) {
            $course  = get_course($item['courseid']);
            $context = \context_course::instance($course->id);
            self::validate_context($context);
            require_capability('moodle/course:manageactivities', $context);

            // 自動補齊到目標週次（解決 numsections 無法用 API 設定的問題）。
            course_create_sections_if_missing($course, $item['section']);

            $mi = new \stdClass();
            $mi->modulename         = 'url';
            $mi->module             = $moduleid;
            $mi->course             = $course->id;
            $mi->section            = $item['section'];
            $mi->visible            = 1;
            $mi->visibleoncoursepage = 1;
            $mi->cmidnumber         = '';
            $mi->groupmode          = 0;
            $mi->groupingid         = 0;
            $mi->name               = $item['name'];
            $mi->introeditor        = ['text' => $item['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
            $mi->showdescription    = 0;

            // url 模組專屬欄位
            $mi->externalurl        = $item['url'];
            $mi->display            = 0;   // RESOURCELIB_DISPLAY_AUTO
            $mi->printintro         = 1;
            $mi->printheading       = 1;
            $mi->parameters         = '';
            $mi->popupwidth         = 620;
            $mi->popupheight        = 450;

            // 完成度：檢視即標記完成（＝打卡）
            $mi->completion              = COMPLETION_TRACKING_AUTOMATIC; // 2
            $mi->completionview          = 1;
            $mi->completionexpected      = 0;
            $mi->completiongradeitemnumber = null;

            // 存取限制：到指定日期才解鎖
            if (!empty($item['availablefrom'])) {
                $mi->availabilityconditionsjson = json_encode([
                    'op'    => '&',
                    'c'     => [['type' => 'date', 'd' => '>=', 't' => (int) $item['availablefrom']]],
                    'showc' => [true],
                ]);
            }

            $created = add_moduleinfo($mi, $course);
            $results[] = [
                'section' => $item['section'],
                'name'    => $item['name'],
                'cmid'    => $created->coursemodule,
            ];
        }

        return $results;
    }

    public static function create_urls_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'section' => new external_value(PARAM_INT, '週次區塊編號'),
                'name'    => new external_value(PARAM_TEXT, '活動名稱'),
                'cmid'    => new external_value(PARAM_INT, '課程模組 id'),
            ])
        );
    }

    // ---------------------------------------------------------------------
    // 批次建立測驗(Quiz) + 由 GIFT 匯入選擇題
    // ---------------------------------------------------------------------

    public static function create_quizzes_parameters() {
        return new external_function_parameters([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'      => new external_value(PARAM_INT, '課程 id'),
                    'section'       => new external_value(PARAM_INT, '週次區塊編號'),
                    'name'          => new external_value(PARAM_TEXT, '測驗名稱'),
                    'gift'          => new external_value(PARAM_RAW, 'GIFT 格式題目'),
                    'intro'         => new external_value(PARAM_RAW, '說明 HTML', VALUE_DEFAULT, ''),
                    'availablefrom' => new external_value(PARAM_INT, '解鎖 unix ts，0=不限', VALUE_DEFAULT, 0),
                ])
            ),
        ]);
    }

    public static function create_quizzes($items) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::create_quizzes_parameters(), ['items' => $items]);
        set_config('enablecompletion', 1);
        set_config('enableavailability', 1);

        $quizmoduleid = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);

        // 複習選項的「時機」位元
        $during = 0x10000; $immed = 0x01000; $later = 0x00100; $closed = 0x00010;
        $reviewattempt = $during | $immed | $later | $closed;
        $reviewother   = $immed | $later | $closed;

        $results = [];
        foreach ($params['items'] as $item) {
            $course  = get_course($item['courseid']);
            $context = \context_course::instance($course->id);
            self::validate_context($context);
            require_capability('moodle/course:manageactivities', $context);

            course_create_sections_if_missing($course, $item['section']);

            // 1) 建測驗模組
            $mi = new \stdClass();
            $mi->modulename = 'quiz';
            $mi->module = $quizmoduleid;
            $mi->course = $course->id;
            $mi->section = $item['section'];
            $mi->visible = 1;
            $mi->visibleoncoursepage = 1;
            $mi->cmidnumber = '';
            $mi->groupmode = 0;
            $mi->groupingid = 0;
            $mi->name = $item['name'];
            $mi->introeditor = ['text' => $item['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
            $mi->showdescription = 0;
            $mi->timeopen = 0; $mi->timeclose = 0; $mi->timelimit = 0;
            $mi->overduehandling = 'autosubmit'; $mi->graceperiod = 0;
            $mi->preferredbehaviour = 'deferredfeedback';
            $mi->canredoquestions = 0;
            $mi->attempts = 0; $mi->attemptonlast = 0;
            $mi->grademethod = 1;
            $mi->decimalpoints = 2; $mi->questiondecimalpoints = -1;
            $mi->shuffleanswers = 1;
            $mi->questionsperpage = 1;
            $mi->navmethod = 'free';
            $mi->quizpassword = '';       // mdl_quiz.password 為 NOT NULL，需給空字串
            $mi->subnet = '';
            $mi->browsersecurity = '-';
            $mi->sumgrades = 0;
            $mi->grade = 10;
            $mi->reviewattempt = $reviewattempt;
            $mi->reviewcorrectness = $reviewother;
            $mi->reviewmaxmarks = $reviewother;
            $mi->reviewmarks = $reviewother;
            $mi->reviewspecificfeedback = $reviewother;
            $mi->reviewgeneralfeedback = $reviewother;
            $mi->reviewrightanswer = $reviewother;
            $mi->reviewoverallfeedback = $reviewother;
            $mi->completion = COMPLETION_TRACKING_MANUAL;

            if (!empty($item['availablefrom'])) {
                $mi->availabilityconditionsjson = json_encode([
                    'op'    => '&',
                    'c'     => [['type' => 'date', 'd' => '>=', 't' => (int) $item['availablefrom']]],
                    'showc' => [true],
                ]);
            }

            $created = add_moduleinfo($mi, $course);
            $quiz = $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST);

            // 2) 題庫分類（課程情境的預設分類）
            $qcategory = question_get_default_category($context->id);
            if (!$qcategory) {
                $qcategory = question_make_default_categories([$context]);
            }

            // 3) 用 Moodle 內建 GIFT 匯入引擎存題目
            $tmp = tempnam(sys_get_temp_dir(), 'gift');
            file_put_contents($tmp, $item['gift']);
            $qformat = new \qformat_gift();
            $qformat->setContexts([$context]);
            $qformat->setCourse($course);
            $qformat->setCategory($qcategory);
            $qformat->setFilename($tmp);
            $qformat->setRealfilename('pf.gift');
            $qformat->setMatchgrades('nearest');
            $qformat->setCatfromfile(false);
            $qformat->setContextfromfile(false);
            $qformat->setStoponerror(true);
            $qformat->setCattofile(false);
            $qformat->setContexttofile(false);

            ob_start();
            $qformat->importprocess();
            ob_end_clean();
            @unlink($tmp);

            $qids = isset($qformat->questionids) ? $qformat->questionids : [];

            // 4) 把題目加進測驗
            foreach ($qids as $qid) {
                quiz_add_quiz_question($qid, $quiz, 0);
            }
            quiz_update_sumgrades($quiz);

            $results[] = [
                'section'   => $item['section'],
                'name'      => $item['name'],
                'cmid'      => $created->coursemodule,
                'questions' => count($qids),
            ];
        }
        return $results;
    }

    public static function create_quizzes_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'section'   => new external_value(PARAM_INT, '週次'),
                'name'      => new external_value(PARAM_TEXT, '測驗名稱'),
                'cmid'      => new external_value(PARAM_INT, '課程模組 id'),
                'questions' => new external_value(PARAM_INT, '匯入題數'),
            ])
        );
    }
}
