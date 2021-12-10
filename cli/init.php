<?php
use auth_entsync\init\console;
use auth_entsync\init\roles;
use auth_entsync\init\h5p;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__ . '/../ent_defs.php');


$help =
"Initialise une instance.
    
Options:
--diag       Affiche un diagnostic.
--only-ko       N'affiche que les erreurs.
--run=<tasks,...> | all
-h, --help            Affiche l'aide.
    
tasks :
 theme : active le thème acparis
    
    
";

list($options, $unrecognised) = cli_get_params(
    [
        'diag'  => false,
        'only-ko'  => false,
        'run'  => false,
        'ent'  => false,
        'help'    => false,
    ],
    [
        'h' => 'help',
    ]
    );

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

$aes_container = \auth_entsync\container::services();
$aes_console = new console(!!$options['only-ko']);

if ($options['diag']) {
    if ($options['only-ko']) $aes_only_ko = true;
    else $aes_only_ko = false;
    cli_heading('Rôles');
    aes_roles::check_roles();
} else if ($options['run']) {
    if (strtolower(trim($options['run'])) === 'all') {
        $actions = [
            'homepage' => true,
            'theme' => true,
            'unoconv' => true,
            'entsync' => true,
            'entsync-roles' => true,
            'entsync-defaults' => true,
            'system-roles' => true,
            'roles-defaults' => true
        ];
    } else {
        $parts = explode(',', $options['run']);
        $actions = [];
        for ($i = 0; $i < count($parts); $i++) {
            $actions[strtolower(trim($parts[$i]))] = true;
        }
    }
    if (array_key_exists('homepage', $actions)) {
        aes_conf::set_homepage();
    }
    if (array_key_exists('theme', $actions)) {
        aes_conf::set_theme();
    }
    if (array_key_exists('unoconv', $actions)) {
        aes_conf::enable_unoconv();
    }
    if (array_key_exists('entsync', $actions)) {
        aes_conf::enable_entsync();
    }
    if (array_key_exists('entsync-roles', $actions)) {
        aes_roles::add_role('catcreator');
        aes_roles::add_role('courseowner');
        aes_roles::sort_roles();
        $cc_role = $DB->get_record('role', ['shortname' => 'catcreator']);
        aes_roles::reset_role_cntx($cc_role);
        aes_roles::reset_role_allow($cc_role);
        aes_roles::reset_role_cap($cc_role);
        $co_role = $DB->get_record('role', ['shortname' => 'courseowner']);
        aes_roles::reset_role_cntx($co_role);
        aes_roles::reset_role_allow($co_role);
        aes_roles::reset_role_cap($co_role);
    }
    if (array_key_exists('system-roles', $actions)) {
        $wk_roles = aes_roles::get_well_known_roles();
        foreach ($wk_roles as $shortname => $sys) {
            if ($sys) {
                $role = $DB->get_record('role', ['shortname' => $shortname]);
                aes_roles::reset_role_cntx($role);
                aes_roles::reset_role_allow($role);
                aes_roles::reset_role_cap($role);
            }
        }
    }
    if (array_key_exists('entsync-defaults', $actions)) {
        $ent = $options['ent'];
        if($ent) {
            aes_conf::configure_entsync($ent);
        } else {
            aes_writeln_ko('Veuillez indiquer l\'ENT pour configurer \'entsync\'.');
        }
    }
    if (array_key_exists('roles-defaults', $actions)) {
        aes_roles::set_default_roles();
    }
    if (array_key_exists('mdl_h5p', $actions)) {
        $aes_h5p = new h5p($aes_console, $aes_container);
        $aes_h5p->fix('mdl_h5p');
    }
    if (array_key_exists('mod_hvp', $actions)) {
        $aes_h5p = new h5p($aes_console, $aes_container);
        $aes_h5p->fix('mod_hvp');
    }
}

exit(0);

function aes_writeln_ok($msg) {
    global $aes_only_ko;
    if (! $aes_only_ko)
        cli_writeln(cli_ansi_format('<colour:green>' . 'OK : ' . '<colour:normal>' . $msg));
}
function aes_writeln_ko($msg) {
    cli_writeln(cli_ansi_format('<colour:red>' . 'KO : ' . '<colour:normal>' . $msg));
}


// Conf
class aes_conf {
    public static function check_homepage() {
        global $CFG;
        if ($CFG->defaulthomepage == HOMEPAGE_SITE) {
            aes_writeln_ok('La page d\'accueil est réglée sur \'Site\'.');
        } else  {
            aes_writeln_ko('La page d\'accueil n\'est pas réglée sur \'Site\'.');
        }
    }
    public static function set_homepage() {
        aes_writeln_ok('Set homepage as default');
        set_config('defaulthomepage', HOMEPAGE_SITE);
        set_config('frontpage', '');
        set_config('frontpageloggedin', '7,2');
        set_config('maxcategorydepth', '1');
    }
    public static function set_theme() {
        aes_writeln_ok('Configure le thème sur \'acparis\'.');
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_LEGACY);
        set_config($themename, null);
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_MOBILE);
        set_config($themename, null);
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_TABLET);
        set_config($themename, null);
        $theme = theme_config::load('acparis');
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_DEFAULT);
        set_config($themename, $theme->name);
    }
    public static function check_unoconv() {
        $type = 'fileconverter';
        $plugin ='unoconv';
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type($type);
        if (! array_key_exists($plugin, $plugins)) {
            aes_writeln_ko('Le plugin \'unoconv\' est absent.');
        } else {
            $plugin = $plugins[$plugin];
            if ($plugin->is_enabled()) {
                aes_writeln_ok('Le plugin \'unoconv\' est déjà activé.');
            } else {
                aes_writeln_ko('Le plugin \'unoconv\' n\'est pas activé.');
            }
        }
    }
    public static function enable_unoconv() {
        $type = 'fileconverter';
        $plugin ='unoconv';
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type($type);
        if (! array_key_exists($plugin, $plugins)) {
            aes_writeln_ko('Le plugin \'unoconv\' est absent.');
        } else {
            $plugin = $plugins[$plugin];
            if ($plugin->is_enabled()) {
                aes_writeln_ok('Le plugin \'unoconv\' est déjà activé.');
            } else {
                aes_writeln_ok('Active le plugin \'unoconv\'.');
                $plugin->set_enabled(true);
            }
        }
    }

    public static function enable_entsync() {
        if (!exists_auth_plugin('entsync')) {
            aes_writeln_ko('Le plugin \'entsync\' est absent.');
        } else {
            if (is_enabled_auth('entsync')) {
                aes_writeln_ok('Le plugin \'entsync\' est déjà activé.');
            } else {
                aes_writeln_ok('Active le plugin \'entsync\'.');
                $authsenabled = get_enabled_auth_plugins(true); // fix the list of enabled auths
                $authsenabled[] = 'entsync';
                $authsenabled = array_unique($authsenabled);
                set_config('auth', implode(',', $authsenabled));
                get_enabled_auth_plugins(true);
                core_plugin_manager::reset_caches();
            }
        }
    }

    public static function configure_entsync($e) {
        global $DB;
        $ent = auth_entsync_ent_base::get_ent($e);
        if ($ent) {
            if ($ent->is_enabled()) {
                aes_writeln_ok('L\'ENT ' . $ent->nomlong . ' est déjà activé.');
            } else {
                aes_writeln_ok('Activation de l\'ENT ' . $ent->nomlong . '.');
                auth_entsync_ent_base::enable_ent($ent->get_code());
            }
        } else {
            aes_writeln_ko('L\'ENT ' . $e .  ' n\'existe pas.');
        }
        $role_ens = get_config('auth_entsync', 'role_ens');
        if (false === $role_ens) $role_ens = 0;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'catcreator']);
        if ($roleid) {
            if ($roleid == $role_ens) {
                aes_writeln_ok('Role système des enseignants est déjà catcreator.');
            } else {
                aes_writeln_ok('Paramètrage du rôle système des enseignants sur catcreator.');
                set_config('role_ens', $roleid, 'auth_entsync');
            }
        } else {
            aes_writeln_ko('Le rôle catcreator n\'existe pas. Impossible de configurer le rôle système des enseignants.');
        }
    }
}

// Roles
class aes_roles {
    protected static $cap = false;
    protected static $archetypes_cap = false;
    
    public static function sort_roles() {
        global $DB;
        aes_writeln_ok('Réordone les rôles');
        $catrole_id = $DB->get_field('role', 'id', ['shortname' => 'catcreator']);
        $ownerrole_id = $DB->get_field('role', 'id', ['shortname' => 'courseowner']);
        
        $rolelst = $DB->get_records('role', [], 'sortorder');
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
        $temp = $DB->get_field('role', 'MAX(sortorder) + 1', []);
        $rec = new stdClass();
        foreach($rolelst as $roleid => $role) {
            if($role->sortorder != $role->neworder) {
                $rec->id = $roleid;
                $rec->sortorder = $temp + $role->sortorder;
                $DB->update_record('role', $rec);
            }
        }
        foreach($rolelst as $roleid => $role) {
            if($role->sortorder != $role->neworder) {
                $rec->id = $roleid;
                $rec->sortorder = $role->neworder;
                $DB->update_record('role', $rec);
            }
        }
    }

    public static function check_roles() {
        global $DB;
        global $CFG;
        $roles = $DB->get_records('role');
        $wk_roles = self::get_well_known_roles();
        $unkn = [];
        $to_check = [];
        $existings = [];
        $duplicates = [];
        foreach ($roles as $role) {
            if (array_key_exists($role->shortname, $existings)) {
                $duplicates[] = $role->shortname;
            } else {
                $existings[$role->shortname] = $role->id;
                if (array_key_exists($role->shortname, $wk_roles)) {
                    $to_check[] = $role;
                } else {
                    $unkn[] = $role->shortname;
                }
            }
        }
        if (empty($duplicates)) {
            aes_writeln_ok('Aucun rôle dupliqué.');
        } else {
            aes_writeln_ko('Rôles dupliqués : ' . implode(', ', $duplicates));
        }
        if (empty($unkn)) {
            aes_writeln_ok('Aucun rôle inconnu.');
        } else {
            aes_writeln_ko('Rôles inconnus : ' . implode(', ', $unkn));
        }
        $missings = [];
        $sys_missings = [];
        foreach ($wk_roles as $rolename => $buildin) {
            if (! array_key_exists($rolename, $existings)) {
                if ($buildin) {
                    $sys_missings[] = $rolename;
                } else {
                    $missings[] = $rolename;
                }
            }
        }
        if (empty($sys_missings)) {
            aes_writeln_ok('Aucun rôle système manquant.');
        } else {
            aes_writeln_ko('Rôles système manquants : ' . implode(', ', $missings));
        }
        if (empty($missings)) {
            aes_writeln_ok('Aucun rôle entsync manquant.');
        } else {
            aes_writeln_ko('Rôles entsync manquants : ' . implode(', ', $missings));
        }
        foreach ($to_check as $role) {
            self::check_role($role);
        }
        
        $restorersnewrole = $CFG->restorernewroleid;
        $creatornewrole = $CFG->creatornewroleid;
        
        $wanted_defaults = self::get_wanted_defaults();
        if (array_key_exists($wanted_defaults['new'], $existings)) {
            if($existings[$wanted_defaults['new']] == $creatornewrole) {
                aes_writeln_ok('Rôle par défaut des nouveaux cours conforme.');
            } else {
                aes_writeln_ko('Rôle par défaut des nouveaux cours non conforme.');
                //cli_writeln($creatornewrole);
                //cli_writeln($existings[$wanted_defaults['new']]);
            }
        } else {
            aes_writeln_ko('Rôle par défaut des nouveaux cours non conforme car non dispo.');
        }
        if (array_key_exists($wanted_defaults['restore'], $existings)) {
            if($existings[$wanted_defaults['restore']] == $restorersnewrole) {
                aes_writeln_ok('Rôle par défaut des cours restaurés conforme.');
            } else {
                aes_writeln_ko('Rôle par défaut des cours restaurés non conforme.');
                //cli_writeln($restorersnewrole);
                //cli_writeln($existings[$wanted_defaults['restore']]);
            }
        } else {
            aes_writeln_ko('Rôle par défaut des cours restaurés non conforme car non dispo.');
        }
    }
    
    public static function check_role($role) {
        self::check_role_cntx($role);
        self::check_role_allow($role);
        self::check_role_cap($role);
    }
    
    public static function add_role($roleShortName) {
        global $DB;
        if ($DB->record_exists('role', ['shortname' => $roleShortName])) {
            aes_writeln_ok('Le rôle ' . $roleShortName . ' existe déjà.');
            return;
        }
        $def = self::get_entsync_roles_def($roleShortName);
        if (! $def) {
            aes_writeln_ko('Le rôle ' . $roleShortName . ' n\'est pas un rôle entsync.');
            return;
        }
        aes_writeln_ok('Ajout du rôle ' . $roleShortName);
        create_role($def['name'], $roleShortName, $def['desc'], $def['archetype']);
    }
    
    public static function check_role_cntx($role) {
        global $DB;
        $roleid = $role->id;
        $existing_rec = $DB->get_records('role_context_levels', ['roleid' => $roleid]);
        $wanted = self::get_wanted_cntx($role);
        $wanted_index = [];
        foreach ($wanted as $cntx) {
            $wanted_index[$cntx] = true;
        }
        $extra = [];
        $existings = [];
        $duplicates = [];
        foreach ($existing_rec as $rec) {
            if (array_key_exists($rec->contextlevel, $existings)) {
                $duplicates[] = $rec->contextlevel;
            } else {
                $existings[$rec->contextlevel] = true;
                if (! array_key_exists($rec->contextlevel, $wanted_index)) {
                    $extra[] = $rec->contextlevel;
                }
            }
        }
        $missing = [];
        foreach ($wanted as $cntx) {
            if (! array_key_exists($cntx, $existings)) {
                $missing[] = $cntx;
            }
        }
        if (empty($duplicates) && empty($extra) && empty($missing)) {
            aes_writeln_ok('Rôle ' . $role->shortname . ' contextes conforme.');
        } else {
            $duplicates_cnt = count($duplicates);
            $extra_cnt = count($extra);
            $missing_cnt = count($missing);
            aes_writeln_ko('Rôle ' . $role->shortname . ' contextes non conforme : duplic : ' . $duplicates_cnt . ', extra : ' . $extra_cnt . ', missing : ' . $missing_cnt);
        }
    }
    
    public static function reset_role_cntx($role) {
        $roleid = $role->id;
        set_role_contextlevels($roleid, self::get_wanted_cntx($role));
    }
    
    public static function check_role_allow($role) {
        global $DB;
        $roleid = $role->id;
        foreach (['assign', 'override', 'switch', 'view'] as $type) {
            $field = 'allow'.$type;
            $existing_rec = $DB->get_records('role_allow_' . $type, array('roleid'=>$roleid));
            $wanted = self::get_wanted_role_allows($role, $type);
            $wanted_index = [];
            foreach ($wanted as $id) {
                $wanted_index[$id] = true;
            }
            $extra = [];
            $existings = [];
            $duplicates = [];
            foreach ($existing_rec as $rec) {
                if (array_key_exists($rec->$field, $existings)) {
                    $duplicates[] = $rec->$field;
                } else {
                    $existings[$rec->$field] = true;
                    if (! array_key_exists($rec->$field, $wanted_index)) {
                        $extra[] = $rec->$field;
                    }
                }
            }
            
            $missing = [];
            foreach ($wanted as $id) {
                if (! array_key_exists($id, $existings)) {
                    $missing[] = $id;
                }
            }
            if (empty($duplicates) && empty($extra) && empty($missing)) {
                aes_writeln_ok('Rôle ' . $role->shortname . ' allow '. $type . ' conforme.');
            } else {
                $duplicates_cnt = count($duplicates);
                $extra_cnt = count($extra);
                $missing_cnt = count($missing);
                aes_writeln_ko('Rôle ' . $role->shortname . ' allow '. $type . ' non conforme : duplic : ' . $duplicates_cnt . ', extra : ' . $extra_cnt . ', missing : ' . $missing_cnt);
            }
        }
    }
    
    public static function reset_role_allow($role) {
        global $DB;
        $systemcontext = context_system::instance();
        $roleid = $role->id;
        foreach (['assign', 'override', 'switch', 'view'] as $type) {
            $sql = "SELECT r.*
FROM {role} r
JOIN {role_allow_{$type}} a ON a.allow{$type} = r.id
WHERE a.roleid = :roleid
ORDER BY r.sortorder ASC";
            $current = array_keys($DB->get_records_sql($sql, array('roleid'=>$roleid)));
            
            $addfunction = "core_role_set_{$type}_allowed";
            $deltable = 'role_allow_'.$type;
            $field = 'allow'.$type;
            $eventclass = "\\core\\event\\role_allow_" . $type . "_updated";
            
            $wanted = self::get_wanted_role_allows($role, $type);
            
            foreach ($current as $sroleid) {
                if (in_array($sroleid, $wanted)) {
                    $key = array_search($sroleid, $wanted);
                    unset($wanted[$key]);
                } else {
                    $DB->delete_records($deltable, array('roleid'=>$roleid, $field=>$sroleid));
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
        $systemcontext->mark_dirty();
    }
    
    public static function check_role_cap($role) {
        global $DB;
        $roleid = $role->id;
        $systemcontext = context_system::instance();
        $existing_rec = $DB->get_records('role_capabilities', ['roleid' => $roleid, 'contextid' => $systemcontext->id]);
        $wanted = self::get_wanted_cap($role);
        $wrong = [];
        $existings = [];
        $duplicates = [];
        foreach ($existing_rec as $rec) {
            if (array_key_exists($rec->capability, $existings)) {
                $duplicates[] = $rec->capability;
            } else {
                $existings[$rec->capability] = $rec->permission;
            }
            
            if (array_key_exists($rec->capability, $wanted)) {
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
            if (! array_key_exists($cap, $existings)) {
                if ($perm != CAP_INHERIT) {
                    $wrong[] = $cap;
                }
            }
        }
        if (empty($duplicates) && empty($wrong)) {
            aes_writeln_ok('Rôle ' . $role->shortname . ' capability conforme.');
        } else {
            $duplicates_cnt = count($duplicates);
            $wrong_cnt = count($wrong);
            $wrong_list = implode(', ', $wrong);
            aes_writeln_ko('Rôle ' . $role->shortname . ' capability non conforme : duplic : ' . $duplicates_cnt . ', wrong : ' . $wrong_list);
        }
        
    }
    
    public static function reset_role_cap($role) {
        global $DB;
        $systemcontext = context_system::instance();
        $roleid = $role->id;
        
        $wanted = self::get_wanted_cap($role);
        foreach ($wanted as $cap => $perm) $wanted[$cap] = $perm; //CAP_ALLOW;
        
        $current = $DB->get_records_menu('role_capabilities', array('roleid' => $roleid,
            'contextid' => $systemcontext->id), '', 'capability,permission');
        foreach($current as $cap => $perm) {
            if(!array_key_exists($cap, $wanted)) {
                unassign_capability($cap, $roleid, $systemcontext->id);
            }
        }
        foreach($wanted as $cap => $perm) {
            assign_capability($cap, $perm, $roleid, $systemcontext->id, true);
        }
        $systemcontext->mark_dirty();
    }
    
    public static function get_well_known_roles() {
        return [
            'manager' => true,
            'coursecreator' => true,
            'editingteacher' => true,
            'teacher' => true,
            'student' => true,
            'guest' => true,
            'user' => true,
            'frontpage' => true,
            'catcreator' => false,
            'courseowner' => false,
        ];
    }
    
    public static function get_entsync_roles_def($roleShortName) {
        if ($roleShortName === 'catcreator') {
            return [
                'name' => 'Créateur de cours et catégories',
                'desc' => 'Les créateurs de cours et catégories peuvent créer de nouveaux cours et de nouvelles catégories de cours',
                'archetype' => 'coursecreator'
            ];
        } else if ($roleShortName === 'courseowner') {
            return [
                'name' => 'Propriétaire du cours',
                'desc' => 'Le propriétaire du cours peut le gérer et le supprimer',
                'archetype' => 'editingteacher'
            ];
        } else {
            return false;
        }
    }
    
    public static function get_wanted_role_allows($role, $type) {
        global $DB;
        $ret = get_default_role_archetype_allows($type, $role->archetype);
        $roles = $DB->get_records('role');
        $map = [];
        foreach ($roles as $r) {
            $map[$r->shortname] = $r->id;
        }
    

        if ($type === 'assign') {
            if ($role->shortname === 'editingteacher') {
                $ret[$map['editingteacher']] = $map['editingteacher'];
            } else if ($role->shortname === 'courseowner') {
                $ret[$map['editingteacher']] = $map['editingteacher'];
                $ret[$map['courseowner']] = $map['courseowner'];
            }
        }
        return $ret;
    }
    
    public static function get_wanted_cntx($role) {
        return get_default_contextlevels($role->archetype);
    }
    
    public static function get_wanted_cap($role) {
        $entsync_def = self::get_entsync_roles_def($role->shortname);
        if (empty($entsync_def)) {
            $wanted = self::get_default_capabilities($role->archetype);
        } else {
            $wanted = self::get_default_capabilities($entsync_def['archetype']);
        }
        
        if ($role->shortname === 'catcreator') {
            $wanted['moodle/category:manage'] = CAP_ALLOW;
            $wanted['moodle/course:ignoreavailabilityrestrictions'] = CAP_ALLOW;
        } else if ($role->shortname === 'editingteacher') {
            $wanted['mod/hvp:installrecommendedh5plibraries'] = CAP_ALLOW;
        } else if ($role->shortname === 'coursecreator') {
            $wanted['moodle/course:ignoreavailabilityrestrictions'] = CAP_ALLOW;
        } else if ($role->shortname === 'courseowner') {
            $wanted['mod/hvp:installrecommendedh5plibraries'] = CAP_ALLOW;
            $wanted['moodle/course:ignoreavailabilityrestrictions'] = CAP_ALLOW;
            $wanted['moodle/course:delete'] = CAP_ALLOW;
        } else if ($role->shortname === 'user') {
            $wanted['webservice/rest:use'] = CAP_ALLOW;
        } else if ($role->shortname === 'guest') {
            //$wanted['report/usersessions:manageownsessions'] = CAP_PROHIBIT;
        }
        return $wanted;
    }

    public static function set_default_roles() {
        global $CFG;
        global $DB;
        $creatornewroleid = $CFG->creatornewroleid;
        $restorersnewroleid = $CFG->restorernewroleid;
        $wanted_defaults = self::get_wanted_defaults();
        $wanted_creatornewroleid = $DB->get_field('role', 'id', ['shortname' => $wanted_defaults['new']]);
        $wanted_restorersnewroleid = $DB->get_field('role', 'id', ['shortname' => $wanted_defaults['restore']]);
        if ($wanted_creatornewroleid) {
            if($wanted_creatornewroleid == $creatornewroleid) {
                aes_writeln_ok('Rôle par défaut des nouveaux cours déjà OK.');
            } else {
                aes_writeln_ok('Paramétrage du rôle par défaut des nouveaux cours.');
                set_config('creatornewroleid', $wanted_creatornewroleid);
            }
        } else {
            aes_writeln_ko('Rôle par défaut des nouveaux cours non conforme car non dispo.');
        }
        if ($wanted_restorersnewroleid) {
            if($wanted_restorersnewroleid == $restorersnewroleid) {
                aes_writeln_ok('Rôle par défaut des cours restaurés déjà OK.');
            } else {
                aes_writeln_ok('Paramétrage du rôle par défaut des cours restaurés.');
                set_config('restorernewroleid', $wanted_restorersnewroleid);
            }
        } else {
            aes_writeln_ko('Rôle par défaut des cours restaurés non conforme car non dispo.');
        }
    }

    public static function get_wanted_defaults() {
        return [
            'new' => 'courseowner',
            'restore' => 'courseowner',
        ];
    }
    
    public static function get_cap_definitions() {
        if (self::$cap) return self::$cap;
        $components = array_unique(core_component::get_component_names());
        $_aes_cap = load_capability_def('moodle');
        foreach ($components as $c ) {
            if ($c !== 'moodle') $_aes_cap = array_merge($_aes_cap, load_capability_def($c));
        }
        self::$cap = [];
        foreach ($_aes_cap as $name=>$def) {
            $def['name'] = $name;
            self::$cap[] = $def;
        }
        return self::$cap;
    }
    
    public static function get_archetypes_cap() {
        if (self::$archetypes_cap) return self::$archetypes_cap; // $aes_archetypes_cap;
        $alldefs = self::get_cap_definitions();
        $allcaps = [];
        foreach ($alldefs as $def) $allcaps[$def['name']] = false;
        $limit = count($alldefs);
        $limit = 4 * $limit;
        $i = 0;
        $notfound = [];
        while ($def = array_pop($alldefs)) {
            if ($i++ > $limit) cli_error('Clone permission recurses too deep.');
            $name = $def['name'];
            $clone_found = false;
            if (array_key_exists('clonepermissionsfrom', $def)) {
                $name_to_clone = $def['clonepermissionsfrom'];
                if (array_key_exists($name_to_clone, $allcaps)) {
                    $clone_found = true;
                    $def_to_clone = $allcaps[$name_to_clone];
                    if (false === $def_to_clone) {
                        array_unshift($alldefs, $def);
                    } else {
                        $allcaps[$name] = $def_to_clone;
                    }
                } else {
                    $notfound[$name_to_clone] = true;
                }
            }
            if (! $clone_found) {
                if (array_key_exists('archetypes', $def)) {
                    $allcaps[$name] = $def['archetypes'];
                } else if (array_key_exists('legacy', $def)) {
                    $allcaps[$name] = $def['legacy'];
                } else {
                    $allcaps[$name] = [];
                }
            }
        }
        /*if (count($notfound) > 0) {
         $notfound = array_keys($notfound);
         cli_writeln('unknown clones : ' . implode(', ', $notfound));
         }*/
        self::$archetypes_cap = [];
        foreach ($allcaps as $name=>$def) {
            foreach ($def as $archetype => $perm) {
                if (! array_key_exists($archetype, self::$archetypes_cap)) self::$archetypes_cap[$archetype] = [];
                self::$archetypes_cap[$archetype][$name] = $perm;
            }
        }
        return self::$archetypes_cap;
    }
    
    public static function get_default_capabilities($archetype) {
        $archetypes_cap = self::get_archetypes_cap();
        if (array_key_exists($archetype, $archetypes_cap)) return $archetypes_cap[$archetype];
        else return [];
    }
}