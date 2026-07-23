<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_clubws_create_urls' => [
        'classname'    => 'local_clubws\external',
        'methodname'   => 'create_urls',
        'description'  => 'Batch-create URL activities (with view-completion and date availability).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_clubws_create_quizzes' => [
        'classname'    => 'local_clubws\external',
        'methodname'   => 'create_quizzes',
        'description'  => 'Batch-create quizzes and import multiple-choice questions from GIFT.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
];
