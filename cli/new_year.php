<?php
namespace aes\new_year;

use auth_entsync\init\console;
use auth_entsync\container;

define('CLI_SCRIPT', true);
//ce script supprime toutes les cohortes [DIV] & [GR] gérée par entsync
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'only-ko' => false,
        'diag' => false,
        'run' => false,
        'help' => false,
    ], [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Bascule d'année.

Options:
 -h, --help                Print out this help
 --run=action              execute l'action

EOT;

    echo $help;
    die;
}

$diag = !!$options['diag'];

$container = container::services();
$console = new console(!!$options['only-ko']);


switch ($options['run']) {
    case 'list-entsync-cohorts':
        /** @var \auth_entsync\directory\cohorts $entsync_cohorts */
        $entsync_cohorts = $container->query('directory.cohorts');
        foreach ($entsync_cohorts->list() as $c) {
            if ($c->is_div_or_group()) {
                cli_writeln($c->name);
            }
        }
        break;
    case 'delete-entsync-cohorts':
        if ($diag) {
            $console->write_fix('Diag non implémenté', false);
            break;
        }
        /** @var \auth_entsync\directory\cohorts $entsync_cohorts */
        $entsync_cohorts = $container->query('directory.cohorts');
        foreach ($entsync_cohorts->list() as $c) {
            if ($c->is_div_or_group()) {
                $console->write_fix('delete cohort : ' . $c->name);
                $c->delete();
            }
        }
        break;
    case 'convert-cohort-enrol':
        $enrol_fix = new enrol_fix($console, $container);
        $enrol_fix->convert_cohort_to_manual($diag);
        break;
    case 'restore-student-role':
        $enrol_fix = new enrol_fix($console, $container);
        $enrol_fix->restore_student_role($diag);
        break;
}

/** Gére les inscriptions par synchronisation de cohorte */
class enrol_fix {
    protected console $console;
    /** @var \moodle_database $db */
    protected $db;
    protected $cfg;
    /** @var \auth_entsync\conf $conf */
    protected $conf;
    /** @var  \enrol_cohort_plugin $enrol_cohort_plugin */
    protected $enrol_cohort_plugin;
    /** @var  \enrol_manual_plugin $enrol_manual_plugin */
    protected $enrol_manual_plugin;

    public function __construct($console, container $container)
    {
        $this->console = $console;
        $this->db = $container->query('DB');
        $this->cfg = $container->query('CFG');
        $this->conf = $container->query('conf');
        $this->enrol_manual_plugin = \enrol_get_plugin('manual');
        $this->enrol_cohort_plugin = \enrol_get_plugin('cohort');
    }

    protected function get_courses_with_entsync_cohort_enrol()
    {
        $sql =
'SELECT e.id as id, c.id as cohortid, e.courseid as courseid
FROM {enrol} e
JOIN {cohort} c ON e.customint1 = c.id
WHERE e.enrol = \'cohort\'
AND c.component = :pn;';
        $list =  $this->db->get_records_sql($sql, ['pn' => $this->conf->pn()]);
        $courses = [];
        foreach ($list as $item) {
            if (\array_key_exists($item->courseid, $courses)) {
                $course = $courses[$item->courseid];
            } else {
                $course = new \stdClass();
                $courses[$item->courseid] = $course;
                $course->id = $item->courseid;
                $course->cohort_enrols = [];
            }
            $course->cohort_enrols[$item->id] = $item->cohortid;
        }
        return $courses;
    }

    protected function get_enroled_users($enrolids)
    {
        list($sql, $params) = $this->db->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'enrolid');
        $sql = "select userid as id from {user_enrolments} where enrolid {$sql} group by userid";
        return $this->db->get_records_sql($sql, $params);
    }

    public function convert_cohort_to_manual($diag)
    {
        $courses = $this->get_courses_with_entsync_cohort_enrol();
        if (\count($courses) == 0) {
            $this->console->write_fix('Pas de synchronisation de cohorte.');
            return;
        }
        foreach ($courses as $courseid => $enrols) {
            $this->console->write_fix('Cours : ' . $courseid, false);
            $manual_enrol = $this->db->get_record('enrol', ['enrol' => 'manual', 'courseid' => $courseid]);
            if ($manual_enrol) {
                $this->console->write_fix('  Manual : ' . $manual_enrol->id);
            } else {
                $course = new \stdClass();
                $course->id = $courseid;
                if (! $diag) $manual_enrol_id = $this->enrol_manual_plugin->add_default_instance($course);
                $this->console->write_fix('  New Manual : ' . $manual_enrol_id, false);
                $manual_enrol = $this->db->get_record('enrol', ['enrol' => 'manual', 'courseid' => $courseid]);
            }
            if (!$manual_enrol) {
                $this->console->write_fix('  !!! pas de manual !!!', false);
                return;
            }
            $enrolids = [];
            foreach ($enrols->cohort_enrols as $enrolid => $cohortid) {
                $enrolids[] = $enrolid;
            }
            $users = $this->get_enroled_users($enrolids);
            foreach ($users as $userid => $user) {
                if (! $diag) $this->enrol_manual_plugin->enrol_user($manual_enrol, $userid, null, 0, 0, \ENROL_USER_SUSPENDED);
                $this->console->write_fix('  User : ' . $userid, false);
            }
        }
    }

    public function restore_student_role($diag)
    {
        $student_role = $this->db->get_record('role', ['shortname' => 'student']);
        if (!$student_role) {
            $this->console->write_fix('pas de role student', false);
            return;
        }
        $min = 1661788800;
        $max = 1661792400;
        $sql =
'WITH cc as (SELECT c.id as courseid, ctx.id as contextid
FROM {course} c
JOIN {context} ctx on ctx.instanceid = c.id and ctx.contextlevel = 50)
SELECT ue.id as id, ue.userid as userid, e.courseid as courseid,
ue.status as status, ue.timecreated as timecreated
FROM {user_enrolments} ue
JOIN {enrol} e on e.id = ue.enrolid and e.enrol = \'manual\'
JOIN cc on cc.courseid = e.courseid
WHERE ue.timecreated > :min AND ue.timecreated < :max AND
not exists (select 1 from {role_assignments} ra where ra.contextid = cc.contextid and ra.userid = ue.userid);';

        $enrols = $this->db->get_records_sql($sql, ['min' => $min, 'max' => $max]);
        if (\count($enrols) == 0) {
            $this->console->write_fix('Pas de PB');
            return;
        }
        $this->console->write_fix('PB : ' . \count($enrols), false);
        $min = PHP_INT_MAX;
        $max = 0;
        $contexts = [];
        foreach ($enrols as $enrol) {
            if (!\array_key_exists($enrol->courseid, $contexts)) {
                $contexts[$enrol->courseid] = \context_course::instance($enrol->courseid);
            }

        }
        foreach ($enrols as $enrol) {
            if ($enrol->timecreated > $max) $max = $enrol->timecreated;
            if ($enrol->timecreated < $min) $min = $enrol->timecreated;
            if (!$diag) \role_assign($student_role->id, $enrol->userid, $contexts[$enrol->courseid]);
        }
        $max_str = $max == 0 ? 'jamais' : $this->console->format_time($max);
        $min_str = $min == PHP_INT_MAX ? 'jamais' : $this->console->format_time($min);
        $this->console->write_fix("de {$min_str} ({$min}) à {$max_str} ({$max})", false);
    }
}