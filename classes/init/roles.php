<?php
namespace auth_entsync\init;
use auth_entsync\init\console;

defined('MOODLE_INTERNAL') || die();

class roles {
    protected console $console;
    public function __construct($console) {
        $this->console = $console;
    }
    protected function start_section() {
        $this->console->start_section('Rôles');
    }
    public function check() {
        $this->start_section();
        $this->console->writeln('Check non implémenté.');
    }
    public function init() {
        $this->start_section();
        $this->console->writeln('Init non implémenté.');
    }
    public function fix() {
        $this->start_section();
        $this->console->writeln('Fix non implémenté.');
    }
}
