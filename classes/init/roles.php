<?php
namespace auth_entsync\init;
use auth_entsync\init\console;
use auth_entsync\container;

defined('MOODLE_INTERNAL') || die();

class roles {
    const WANTED_DEFAULTS = [
        'new' => 'courseowner',
        'restore' => 'courseowner',
    ];

    const ENTSYNC_ROLES_DEFS = [
        'catcreator' => [
            'name' => 'Créateur de cours et catégories',
            'desc' => 'Les créateurs de cours et catégories peuvent créer de nouveaux cours et de nouvelles catégories de cours',
            'archetype' => 'coursecreator'
        ],
        'courseowner' => [
            'name' => 'Propriétaire du cours',
            'desc' => 'Le propriétaire du cours peut le gérer et le supprimer',
            'archetype' => 'editingteacher'
        ]
    ];

    const LIBRARY_ROLES_DEFS = [
        'libcontrib' => [
            'name' => 'Contribution à la bibliothèque',
            'desc' => 'Ce rôle permet de contribur à la bibliothèque',
            'archetype' => 'coursecreator'
        ],
        'libview' => [
            'name' => 'Accès à la bibliothèque',
            'desc' => 'Ce rôle permet de voir tous les cours en tant qu\'enseignant non éditeur',
            'archetype' => 'coursecreator'
        ],
        'libfrontpage' => [
            'name' => 'Accès frontpage bibliothèque',
            'desc' => 'Rôle pour la page d\'accueil',
            'archetype' => 'student'
        ],
    ];

    const ARCHETYPES = [
        'manager',
        'coursecreator',
        'editingteacher',
        'teacher',
        'student',
        'guest',
        'user',
        'frontpage'
    ];

    const EXTRA_ALLOW = [
        'catcreator' => [
            'capabilities' => [
                'moodle/category:manage' => \CAP_ALLOW,
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_ALLOW,
            ],
        ],
        'editingteacher' => [
            'assign' => ['editingteacher'],
            'capabilities' => [
                'mod/hvp:installrecommendedh5plibraries' => \CAP_ALLOW,
            ],
        ],
        'courseowner' => [
            'assign' => ['editingteacher', 'courseowner'],
            'capabilities' => [
                'mod/hvp:installrecommendedh5plibraries' => \CAP_ALLOW,
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_ALLOW,
                'moodle/course:delete' => \CAP_ALLOW,
                'moodle/course:view' => \CAP_ALLOW,
            ],
        ],
        'coursecreator' => [
            'capabilities' => [
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_ALLOW,
            ],
        ],
        'user' => [
            'capabilities' => [
                'webservice/rest:use' => \CAP_ALLOW,
            ],

        ],
        'libcontrib' => [
            'capabilities_copy_from' => [
                'coursecreator',
                'teacher',
            ],
            'capabilities' => [
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_ALLOW,
                'moodle/course:view' => \CAP_ALLOW,
            ],
        ],
        'libview' => [
            'capabilities_copy_from' => [
                'teacher',
            ],
            'capabilities' => [
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_ALLOW,
                'moodle/course:view' => \CAP_ALLOW,
            ],
        ],
        'libfrontpage' => [
            'capabilities_shield_from' => [
                'coursecreator' => \CAP_PROHIBIT,
                'teacher' => \CAP_PROHIBIT,
            ],
            'capabilities_shield' => [
                'moodle/course:ignoreavailabilityrestrictions' => \CAP_PROHIBIT,
                'moodle/course:view' => \CAP_PROHIBIT,
            ],
        ],
    ];

    protected console $console;
    /** @var \moodle_database $db */
    protected $db;
    protected $cfg;
    /** @var \auth_entsync\conf $conf */
    protected $conf;
    protected $cap = false;
    protected $archetypes_cap = false;
    protected $role_map = false;

    public function __construct($console, container $container) {
        $this->console = $console;
        $this->db = $container->query('DB');
        $this->cfg = $container->query('CFG');
        $this->conf = $container->query('conf');
    }
    protected function start_section() {
        $this->console->start_section('Rôles');
    }
    public function check($lib_mode = false) {
        $this->start_section();
        $this->check_roles($lib_mode);
        $this->check_default_roles();
    }
    public function init() {
        $this->start_section();
        if ($this->conf->is_gw()) {
            $this->console->writeln('Init rôles non effectué sur gw.');    
        } else {
            $this->console->writeln('Init rôles non implémenté.');
        }
    }
    public function fix($lib_mode = false) {
        $this->start_section();
        if ($this->conf->is_gw()) {
            $this->console->writeln('Fix rôles non effectué sur gw.');    
        } else {
            $this->add_entsync_roles($lib_mode);
            $this->fix_roles($lib_mode);
            $this->fix_default_roles();
        }
    }

    protected function sort_roles() {
        $catrole_id = $this->db->get_field('role', 'id', ['shortname' => 'catcreator']);
        $ownerrole_id = $this->db->get_field('role', 'id', ['shortname' => 'courseowner']);
        $rolelst = $this->db->get_records('role', [], 'sortorder');
        if($catrole_id) {
            $catrole = $rolelst[$catrole_id];
            unset($rolelst[$catrole_id]);
        }
        if($ownerrole_id) {
            $ownerrole = $rolelst[$ownerrole_id];
            unset($rolelst[$ownerrole_id]);
        }
        
        $i=1;
        foreach($rolelst as $roleid => $role) {
            switch($role->shortname) {
                case 'editingteacher' :
                    if($ownerrole_id) {
                        $ownerrole->neworder = $i++;
                    }
                    break;
                case 'coursecreator' :
                    if($catrole_id) {
                        $catrole->neworder = $i++;
                    }
                    break;
            }
            $role->neworder = $i++;
        }
        if($catrole_id) {
            $rolelst[$catrole_id] = $catrole;
        }
        if($ownerrole_id) {
            $rolelst[$ownerrole_id] = $ownerrole;
        }
        $temp = $this->db->get_field('role', 'MAX(sortorder) + 1', []);
        $rec = new \stdClass();
        foreach($rolelst as $roleid => $role) {
            if($role->sortorder != $role->neworder) {
                $rec->id = $roleid;
                $rec->sortorder = $temp + $role->sortorder;
                $this->db->update_record('role', $rec);
            }
        }
        foreach($rolelst as $roleid => $role) {
            if($role->sortorder != $role->neworder) {
                $rec->id = $roleid;
                $rec->sortorder = $role->neworder;
                $this->db->update_record('role', $rec);
            }
        }
        $this->console->write_fix('Rôles réordonnés');
    }

    protected function check_roles($lib_mode = false) {
        $roles = $this->db->get_records('role');
        $unkn = [];
        $to_check = [];
        $existings = [];
        $duplicates = [];
        foreach ($roles as $role) {
            if (\array_key_exists($role->shortname, $existings)) {
                $duplicates[] = $role->shortname;
            } else {
                $existings[$role->shortname] = $role->id;
                if (\in_array($role->shortname, self::ARCHETYPES)) {
                    $to_check[] = $role;
                } else if (\array_key_exists($role->shortname, self::ENTSYNC_ROLES_DEFS)) {
                    $to_check[] = $role;
                } else {
                    if ($lib_mode) {
                        if (\array_key_exists($role->shortname, self::LIBRARY_ROLES_DEFS)) {
                            $to_check[] = $role;
                        } else {
                            $unkn[] = $role->shortname;
                        }
                    } else {
                        $unkn[] = $role->shortname;
                    }
                }
            }
        }
        if (empty($duplicates)) {
            $this->console->write_check('Aucun rôle dupliqué.');
        } else {
            $this->console->write_check('Rôles dupliqués : ' . implode(', ', $duplicates), false);
        }
        if (empty($unkn)) {
            $this->console->write_check('Aucun rôle inconnu.');
        } else {
            $this->console->write_check('Rôles inconnus : ' . implode(', ', $unkn), false);
        }
        $sys_missings = [];
        foreach (self::ARCHETYPES as $rolename) {
            if (! array_key_exists($rolename, $existings)) {
                $sys_missings[] = $rolename;
            }
        }
        if (empty($sys_missings)) {
            $this->console->write_check('Aucun rôle système manquant.');
        } else {
            $this->console->write_check('Rôles système manquants : ' . implode(', ', $sys_missings), false);
        }
        $missings = [];
        foreach (self::ENTSYNC_ROLES_DEFS as $rolename => $_unused) {
            if (! array_key_exists($rolename, $existings)) {
                $missings[] = $rolename;
            }
        }
        if (empty($missings)) {
            $this->console->write_check('Aucun rôle entsync manquant.');
        } else {
            $this->console->write_check('Rôles entsync manquants : ' . implode(', ', $missings), false);
        }
        if ($lib_mode) {
            $lib_missings = [];
            foreach (self::LIBRARY_ROLES_DEFS as $rolename => $_unused) {
                if (! array_key_exists($rolename, $existings)) {
                    $lib_missings[] = $rolename;
                }
            }
            if (empty($lib_missings)) {
                $this->console->write_check('Aucun rôle de bibliothèque manquant.');
            } else {
                $this->console->write_check('Rôles de bibliothèque manquants : ' . implode(', ', $lib_missings), false);
            }
        }

        foreach ($to_check as $role) {
            $this->check_role($role);
        }
    }

    protected function check_role($role) {
        $this->check_role_archetype($role);
        $this->check_role_cntx($role);
        $this->check_role_allow($role);
        $this->check_role_cap($role);
    }

    protected function fix_roles($lib_mode = false) {
        $roles = $this->db->get_records('role');
        $unkn = [];
        $to_check = [];
        $existings = [];
        $duplicates = [];
        foreach ($roles as $role) {
            if (\array_key_exists($role->shortname, $existings)) {
                $duplicates[] = $role->shortname;
            } else {
                $existings[$role->shortname] = $role->id;
            }
            if (\in_array($role->shortname, self::ARCHETYPES)) {
                $to_check[] = $role;
            } else if (\array_key_exists($role->shortname, self::ENTSYNC_ROLES_DEFS)) {
                $to_check[] = $role;
            } else {
                if ($lib_mode) {
                    if (\array_key_exists($role->shortname, self::LIBRARY_ROLES_DEFS)) {
                        $to_check[] = $role;
                    } else {
                        $unkn[] = $role->shortname;
                    }
                } else {
                    $unkn[] = $role->shortname;
                }
            }
        }
        if (!empty($duplicates)) {
            $this->console->write_fix('Attention, rôles dupliqués : ' . implode(', ', $duplicates), false);
        }
        if (!empty($unkn)) {
            $this->console->write_fix('Attention, rôles inconnus non traités : ' . implode(', ', $unkn), false);
        }
        $sys_missings = [];
        foreach (self::ARCHETYPES as $rolename) {
            if (! array_key_exists($rolename, $existings)) {
                $sys_missings[] = $rolename;
            }
        }
        if (!empty($sys_missings)) {
            $this->console->write_fix('Attention, rôles système manquants non ajoutés : ' . implode(', ', $sys_missings), false);
        }
        $missings = [];
        foreach (self::ENTSYNC_ROLES_DEFS as $rolename => $_unused) {
            if (! array_key_exists($rolename, $existings)) {
                $missings[] = $rolename;
            }
        }
        if (!empty($missings)) {
            $this->console->write_fix('Attention, rôles entsync manquants non ajoutés : ' . implode(', ', $missings), false);
        }
        if ($lib_mode) {
            $lib_missings = [];
            foreach (self::LIBRARY_ROLES_DEFS as $rolename => $_unused) {
                if (! array_key_exists($rolename, $existings)) {
                    $lib_missings[] = $rolename;
                }
            }
            if (!empty($lib_missings)) {
                $this->console->write_fix('Attention, rôles lib manquants non ajoutés : ' . implode(', ', $lib_missings), false);
            }
        }
        foreach ($to_check as $role) {
            $this->fix_role($role);
        }
    }

    protected function fix_role($role) {
        $this->console->write_fix('Correction du rôle ' . $role->shortname . ' si nécessaire');
        $this->fix_role_archetype($role);
        $this->fix_role_cntx($role);
        $this->fix_role_allow($role);
        $this->fix_role_cap($role);
    }

    protected function add_entsync_roles($lib_mode = false) {
        foreach (self::ENTSYNC_ROLES_DEFS as $name => $def) {
            $this->add_role($name, $def);
        }
        if ($lib_mode) {
            foreach (self::LIBRARY_ROLES_DEFS as $name => $def) {
                $this->add_role($name, $def);
            }
        }
        $this->sort_roles();
    }

    protected function add_role($roleShortName, $def) {
        if ($this->db->record_exists('role', ['shortname' => $roleShortName])) {
            $this->console->write_fix('Le rôle ' . $roleShortName . ' existe déjà.');
            return;
        }
        if (! $def) {
            $this->console->write_fix('Le rôle ' . $roleShortName . ' n\'est pas un rôle entsync (ou bib).', false);
            return;
        }
        create_role($def['name'], $roleShortName, $def['desc'], $def['archetype']);
        $this->console->write_fix('Rôle ajouté : ' . $roleShortName);
    }
    
    protected function check_role_archetype($role) {
        if ($role->archetype == $this->_get_wanted_archetype($role)) {
            $this->console->write_check('Rôle ' . $role->shortname . ' archetype conforme.');
        } else {
            $this->console->write_check('Rôle ' . $role->shortname . ' archetype non conforme.', false);
        }
    }

    protected function fix_role_archetype($role) {
        if ($role->archetype != $this->_get_wanted_archetype($role)) {
            $this->console->write_fix('Attention rôle ' . $role->shortname . ' archetype non corrigé.', false);
        }
    }

    protected function check_role_cntx($role) {
        $roleid = $role->id;
        $existing_rec = $this->db->get_records('role_context_levels', ['roleid' => $roleid]);
        $wanted = $this->get_wanted_cntx($role);
        $wanted_index = [];
        foreach ($wanted as $cntx) {
            $wanted_index[$cntx] = true;
        }
        $extra = [];
        $existings = [];
        $duplicates = [];
        foreach ($existing_rec as $rec) {
            if (\array_key_exists($rec->contextlevel, $existings)) {
                $duplicates[] = $rec->contextlevel;
            } else {
                $existings[$rec->contextlevel] = true;
                if (! \array_key_exists($rec->contextlevel, $wanted_index)) {
                    $extra[] = $rec->contextlevel;
                }
            }
        }
        $missing = [];
        foreach ($wanted as $cntx) {
            if (! \array_key_exists($cntx, $existings)) {
                $missing[] = $cntx;
            }
        }
        if (empty($duplicates) && empty($extra) && empty($missing)) {
            $this->console->write_check('Rôle ' . $role->shortname . ' contextes conforme.');
        } else {
            $duplicates_cnt = \count($duplicates);
            $extra_cnt = \count($extra);
            $missing_cnt = \count($missing);
            $this->console->write_check('Rôle ' . $role->shortname . ' contextes non conforme : duplic : ' . $duplicates_cnt . ', extra : ' . $extra_cnt . ', missing : ' . $missing_cnt, false);
        }
    }
    
    protected function fix_role_cntx($role) {
        $roleid = $role->id;
        \set_role_contextlevels($roleid, $this->get_wanted_cntx($role));
    }
    
    protected function _check_role_allow_type($role, $type) {
        $field = 'allow'.$type;
        $existing_rec = $this->db->get_records('role_allow_' . $type, ['roleid' => $role->id]);
        $wanted = $this->get_wanted_role_allows($role, $type);
        $wanted_index = [];
        foreach ($wanted as $id) {
            $wanted_index[$id] = true;
        }
        $extra = [];
        $existings = [];
        $duplicates = [];
        foreach ($existing_rec as $rec) {
            if (\array_key_exists($rec->$field, $existings)) {
                $duplicates[] = $rec->$field;
            } else {
                $existings[$rec->$field] = true;
                if (! \array_key_exists($rec->$field, $wanted_index)) {
                    $extra[] = $rec->$field;
                }
            }
        }
        
        $missing = [];
        foreach ($wanted as $id) {
            if (! \array_key_exists($id, $existings)) {
                $missing[] = $id;
            }
        }
        if (empty($duplicates) && empty($extra) && empty($missing)) {
            $this->console->write_check('Rôle ' . $role->shortname . ' allow '. $type . ' conforme.');
        } else {
            $duplicates_cnt = \count($duplicates);
            $extra_cnt = \count($extra);
            $missing_cnt = \count($missing);
            $this->console->write_check('Rôle ' . $role->shortname . ' allow '. $type . ' non conforme : duplic : ' . $duplicates_cnt . ', extra : ' . $extra_cnt . ', missing : ' . $missing_cnt, false);
        }
    }

    protected function check_role_allow($role) {
        foreach (['assign', 'override', 'switch', 'view'] as $type) {
            $this->_check_role_allow_type($role, $type);
        }
    }

    protected function _fix_role_allow_type($role, $type) {
        $systemcontext = \context_system::instance();
        $roleid = $role->id;
        $sql = "SELECT r.*
FROM {role} r
JOIN {role_allow_{$type}} a ON a.allow{$type} = r.id
WHERE a.roleid = :roleid
ORDER BY r.sortorder ASC";
        $current = \array_keys($this->db->get_records_sql($sql, ['roleid'=>$roleid]));
        
        $addfunction = "core_role_set_{$type}_allowed";
        $deltable = 'role_allow_'.$type;
        $field = 'allow'.$type;
        $eventclass = "\\core\\event\\role_allow_" . $type . "_updated";
        
        $wanted = $this->get_wanted_role_allows($role, $type);
        
        foreach ($current as $sroleid) {
            if (\in_array($sroleid, $wanted)) {
                $key = \array_search($sroleid, $wanted);
                unset($wanted[$key]);
            } else {
                $this->db->delete_records($deltable, array('roleid'=>$roleid, $field=>$sroleid));
                $eventclass::create([
                    'context' => $systemcontext,
                    'objectid' => $roleid,
                    'other' => ['targetroleid' => $sroleid, 'allow' => false]
                ])->trigger();
            }
        }
        
        foreach ($wanted as $sroleid) {
            $addfunction($roleid, $sroleid);
            $eventclass::create([
                'context' => $systemcontext,
                'objectid' => $roleid,
                'other' => ['targetroleid' => $sroleid, 'allow' => true]
            ])->trigger();
        }
    }

    protected function fix_role_allow($role) {
        foreach (['assign', 'override', 'switch', 'view'] as $type) {
            $this->_fix_role_allow_type($role, $type);
        }
        $systemcontext = \context_system::instance();
        $systemcontext->mark_dirty();
    }

    public function check_role_cap($role) {
        $wanted = $this->get_wanted_cap($role);
        $this->_check_role_cap($role, $wanted);
    }

    protected function _check_role_cap($role, $wanted) {
        $roleid = $role->id;
        $systemcontext = \context_system::instance();
        $existing_rec = $this->db->get_records('role_capabilities', ['roleid' => $roleid, 'contextid' => $systemcontext->id]);
        $wrong = [];
        $existings = [];
        $duplicates = [];
        foreach ($existing_rec as $rec) {
            if (\array_key_exists($rec->capability, $existings)) {
                $duplicates[] = $rec->capability;
            } else {
                $existings[$rec->capability] = $rec->permission;
            }
            
            if (\array_key_exists($rec->capability, $wanted)) {
                if ($rec->permission != $wanted[$rec->capability]) {
                    $wrong[] = $rec->capability;
                }
            } else {
                if ($rec->permission != CAP_INHERIT) {
                    $wrong[] = $rec->capability;
                }
            }
        }
        
        foreach ($wanted as $cap => $perm) {
            if (! \array_key_exists($cap, $existings)) {
                if ($perm != CAP_INHERIT) {
                    $wrong[] = $cap;
                }
            }
        }
        if (empty($duplicates) && empty($wrong)) {
            $this->console->write_check('Rôle ' . $role->shortname . ' capability conforme.');
        } else {
            $duplicates_cnt = \count($duplicates);
            //$wrong_cnt = \count($wrong);
            $wrong_list = implode(', ', $wrong);
            $this->console->write_check('Rôle ' . $role->shortname . ' capability non conforme : duplic : ' . $duplicates_cnt . ', wrong : ' . $wrong_list, false);
        }
        
    }
    public function fix_role_cap($role) {
        $wanted = $this->get_wanted_cap($role);
        $this->_fix_role_cap($role, $wanted);
    }    

    protected function _fix_role_cap($role, $wanted) {
        $systemcontext = \context_system::instance();
        $roleid = $role->id;
        
        $current = $this->db->get_records_menu('role_capabilities', array('roleid' => $roleid,
            'contextid' => $systemcontext->id), '', 'capability,permission');
        foreach($current as $cap => $perm) {
            if(!\array_key_exists($cap, $wanted)) {
                \unassign_capability($cap, $roleid, $systemcontext->id);
            }
        }
        foreach($wanted as $cap => $perm) {
            \assign_capability($cap, $perm, $roleid, $systemcontext->id, true);
        }
        $systemcontext->mark_dirty();
    }
    
    protected function _get_wanted_archetype($role) {
        if (\array_key_exists($role->shortname, self::ENTSYNC_ROLES_DEFS))
            return self::ENTSYNC_ROLES_DEFS[$role->shortname]['archetype'];
        if (\array_key_exists($role->shortname, self::LIBRARY_ROLES_DEFS))
            return self::LIBRARY_ROLES_DEFS[$role->shortname]['archetype'];
        if (\in_array($role->shortname, self::ARCHETYPES)) {
            return $role->shortname;
        } else {
            return $role->archetype;
        }
    }

    protected function _get_role_map() {
        if (! $this->role_map) {
            $roles = $this->db->get_records('role');
            $this->role_map = [];
            foreach ($roles as $r) {
                $this->role_map[$r->shortname] = $r->id;
            }
        }
        return $this->role_map;
    }

    protected function get_wanted_role_allows($role, $type) {
        $archetype = $this->_get_wanted_archetype($role);
        $ret = \get_default_role_archetype_allows($type, $archetype);
        if (\array_key_exists($role->shortname, self::EXTRA_ALLOW)) {
            $extra_def = self::EXTRA_ALLOW[$role->shortname];
            if (\array_key_exists($type, $extra_def)) {
                $map = $this->_get_role_map();
                foreach ($extra_def[$type] as $name) {
                    $id = $map[$name]; 
                    $ret[$id] = $id;
                }
            }
        }
        return $ret;
    }
    
    protected function get_wanted_cntx($role) {
        $archetype = $this->_get_wanted_archetype($role);
        return \get_default_contextlevels($archetype);
    }
    
    protected function get_wanted_cap($role) {
        if (\array_key_exists($role->shortname, self::EXTRA_ALLOW)) {
            $extra_def = self::EXTRA_ALLOW[$role->shortname];
            if (\array_key_exists('capabilities_copy_from', $extra_def)) {
                $wanted = $this->merge_default_capabilities($extra_def['capabilities_copy_from']);
            } else {
                $archetype = $this->_get_wanted_archetype($role);
                $wanted = $this->merge_default_capabilities([$archetype]);
            }
            if (\array_key_exists('capabilities', $extra_def)) {
                foreach ($extra_def['capabilities'] as $cap => $perm) {
                    $wanted[$cap] = $perm;
                }
            }
            if (\array_key_exists('capabilities_shield_from', $extra_def)) {
                foreach($extra_def['capabilities_shield_from'] as $archetype => $perm) {
                    $caps = $this->get_default_capabilities($archetype);
                    foreach ($caps as $cap => $unused_perm) {
                        if ($unused_perm == \CAP_ALLOW){
                            $do = false;
                            if (\array_key_exists($cap, $wanted)) {
                                $do = ($wanted[$cap] == \CAP_INHERIT);
                            } else {
                                $do = true;
                            }
                            if ($do) {
                                $wanted[$cap] = $perm;
                            }
                        }
                    }
                }
            }
            if (\array_key_exists('capabilities_shield', $extra_def)) {
                foreach($extra_def['capabilities_shield'] as $cap => $perm) {
                    $do = false;
                    if (\array_key_exists($cap, $wanted)) {
                        $do = ($wanted[$cap] == \CAP_INHERIT);
                    } else {
                        $do = true;
                    }
                    if ($do) {
                        $wanted[$cap] = $perm;
                    }
            }
            }
        } else {
            $archetype = $this->_get_wanted_archetype($role);
            return $this->merge_default_capabilities([$archetype]);
        }
        return $wanted;
    }

    protected function check_default_roles() {
        $creatornewroleid = $this->cfg->creatornewroleid;
        $restorersnewroleid = $this->cfg->restorernewroleid;
        $wanted_defaults = self::WANTED_DEFAULTS;
        $wanted_creatornewroleid = $this->db->get_field('role', 'id', ['shortname' => $wanted_defaults['new']]);
        $wanted_restorersnewroleid = $this->db->get_field('role', 'id', ['shortname' => $wanted_defaults['restore']]);
        if ($wanted_creatornewroleid) {
            if($wanted_creatornewroleid == $creatornewroleid) {
                $this->console->write_check('Rôle par défaut des nouveaux cours conforme.');
            } else {
                $this->console->write_check('Rôle par défaut des nouveaux cours non conforme.', false);
            }
        } else {
            $this->console->write_check('Rôle par défaut des nouveaux cours non conforme car non dispo.', false);
        }
        if ($wanted_restorersnewroleid) {
            if($wanted_restorersnewroleid == $restorersnewroleid) {
                $this->console->write_check('Rôle par défaut des cours restaurés conforme.');
            } else {
                $this->console->write_check('Rôle par défaut des cours restaurés non conforme.', false);
            }
        } else {
            $this->console->write_check('Rôle par défaut des cours restaurés non conforme car non dispo.', false);
        }
    }

    protected function fix_default_roles() {
        $creatornewroleid = $this->cfg->creatornewroleid;
        $restorersnewroleid = $this->cfg->restorernewroleid;
        $wanted_defaults = self::WANTED_DEFAULTS;
        $wanted_creatornewroleid = $this->db->get_field('role', 'id', ['shortname' => $wanted_defaults['new']]);
        $wanted_restorersnewroleid = $this->db->get_field('role', 'id', ['shortname' => $wanted_defaults['restore']]);
        if ($wanted_creatornewroleid) {
            if($wanted_creatornewroleid == $creatornewroleid) {
                $this->console->write_fix('Rôle par défaut des nouveaux cours déjà OK.');
            } else {
                set_config('creatornewroleid', $wanted_creatornewroleid);
                $this->console->write_fix('Rôle par défaut des nouveaux cours changé.');
            }
        } else {
            $this->console->write_fix('Rôle par défaut des nouveaux cours non conforme car non dispo.', false);
        }
        if ($wanted_restorersnewroleid) {
            if($wanted_restorersnewroleid == $restorersnewroleid) {
                $this->console->write_fix('Rôle par défaut des cours restaurés déjà OK.');
            } else {
                set_config('restorernewroleid', $wanted_restorersnewroleid);
                $this->console->write_fix('Rôle par défaut des cours restaurés changé.');
            }
        } else {
            $this->console->write_fix('Rôle par défaut des cours restaurés non conforme car non dispo.', false);
        }
    }

    protected function _compute_cap_definitions() {
        $components = \array_unique(\core_component::get_component_names());
        $all_defs = \load_capability_def('moodle');
        foreach ($components as $c ) {
            if ($c !== 'moodle') $all_defs = \array_merge($all_defs, \load_capability_def($c));
        }
        $all_caps = [];
        foreach ($all_defs as $name=>$def) {
            $def['name'] = $name;
            $all_caps[] = $def;
        }
        return $all_caps;
    }

    public function get_cap_definitions() {
        if (empty($this->cap)) {
            $this->cap = $this->_compute_cap_definitions();
        }
        return $this->cap;
    }

    protected function _compute_all_caps() {
        $alldefs = $this->get_cap_definitions();
        $allcaps = [];
        foreach ($alldefs as $def) $allcaps[$def['name']] = false;
        $limit = \count($alldefs);
        $limit = 4 * $limit;
        $i = 0;
        $notfound = [];
        while ($def = \array_pop($alldefs)) {
            if ($i++ > $limit) throw new \Exception('Clone permission recurses too deep.');
            $name = $def['name'];
            $clone_found = false;
            if (\array_key_exists('clonepermissionsfrom', $def)) {
                $name_to_clone = $def['clonepermissionsfrom'];
                if (\array_key_exists($name_to_clone, $allcaps)) {
                    $clone_found = true;
                    $def_to_clone = $allcaps[$name_to_clone];
                    if (false === $def_to_clone) {
                        \array_unshift($alldefs, $def);
                    } else {
                        $allcaps[$name] = $def_to_clone;
                    }
                } else {
                    $notfound[$name_to_clone] = true;
                }
            }
            if (! $clone_found) {
                if (\array_key_exists('archetypes', $def)) {
                    $allcaps[$name] = $def['archetypes'];
                } else if (\array_key_exists('legacy', $def)) {
                    $allcaps[$name] = $def['legacy'];
                } else {
                    $allcaps[$name] = [];
                }
            }
        }
        return $allcaps;
    }

    protected function _compute_archetypes_cap() {
        $archetypes_cap = [];
        foreach ($this->_compute_all_caps() as $name=>$def) {
            foreach ($def as $archetype => $perm) {
                if (! \array_key_exists($archetype, $archetypes_cap)) $archetypes_cap[$archetype] = [];
                $archetypes_cap[$archetype][$name] = $perm;
            }
        }
        return $archetypes_cap;
    }

    public function get_archetypes_cap() {
        if (empty($this->archetypes_cap)) {
            $this->archetypes_cap = $this->_compute_archetypes_cap();
        }
        return $this->archetypes_cap;
    }
    
    public function get_default_capabilities($archetype) {
        $archetypes_cap = $this->get_archetypes_cap();
        if (\array_key_exists($archetype, $archetypes_cap)) return $archetypes_cap[$archetype];
        else return [];
    }

    public function merge_default_capabilities($archetypes) {
        if (\count($archetypes) === 1) return $this->get_default_capabilities($archetypes[0]);
        $ret = [];
        foreach ($archetypes as $archetype) {
            $caps = $this->get_default_capabilities($archetype);
            foreach ($caps as $cap => $perm) {
                if (\array_key_exists($cap, $ret)) {
                    $ret[$cap] = \max($perm, $ret[$cap]);
                } else {
                    $ret[$cap] = $perm;
                }
            }
        }
        return $ret;
    }
}
