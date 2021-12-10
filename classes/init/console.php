<?php
namespace auth_entsync\init;

defined('MOODLE_INTERNAL') || die();

class console {
    protected $only_ko;
    protected $section_heading = false;
    protected $section_started = false;
    public function __construct($only_ko = false) {
        $this->only_ko = $only_ko;
    }
    protected function ensure_section() {
        if ($this->section_started) return;
        if (empty($this->section_heading)) return;
        \cli_heading($this->section_heading);
        $this->section_started = true;
    }
    public function error($text, $errorcode = 1) {
        $this->ensure_section();
        \cli_error($text, $errorcode);
    }
    public function start_section($text) {
        $this->section_started = false;
        $this->section_heading = $text;
    }
    public function write_check($msg, $ok = true) {
        if ($ok) {
            if (! $this->only_ko) {
                $this->writeln_format($msg,'OK', 'green');
            }
        } else {
            $this->writeln_format($msg,'KO', 'red');
        }
    }
    public function write_fix($msg, $ok = true) {
        if ($ok) {
            if (! $this->only_ko) {
                $this->writeln_format($msg,'FIXED', 'green');
            }
        } else {
            $this->writeln_format($msg,'NOT FIXED', 'red');
        }
    }
    public function writeln($text) {
        $this->ensure_section();
        \cli_writeln($text);
    }
    public function writeln_format($msg, $prefix, $color) {
        $this->writeln(\cli_ansi_format("<colour:{$color}>{$prefix} :<colour:normal> {$msg}"));
    }
}