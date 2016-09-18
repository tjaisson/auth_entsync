<?php

namespace auth_entsync\task;

class garbage extends \core\task\scheduled_task {      
    public function get_name() {
        return get_string('garbage', 'auth_entsync');
    }
                                                                     
    public function execute() {       
    }                                                                                                                               
}