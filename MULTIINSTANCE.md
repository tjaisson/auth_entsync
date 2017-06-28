# Config multi instance
=============================


## Notes liées à wamp
wampserver definit par defaut un virtualhost sur son www. Je ne sais pas pourquoi.
Du coup les rewrite définis au niveau serveur ne fonctionnent pas.

Pour qu'une clause RewriteRule substitue vers le système de fichier, il faut que le début
de la substitution existe dans le système de fichier et commence par 'C:\\foo\\bar/suite'
## httpd.conf
    RewriteEngine On  
    RewriteRule "^/m(\d)(\d)\.inst\d\d(.*)$"  "c:\\Users\\Tom/moodledev/www/moodle-$1.$2$3" [L]


## Moodle config file
```php
<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$pamroot = 'http://localhost';
$pamdataroot = 'C:\\Users\\Tom\\moodledev\\data\\';
list(,$repertoire) = explode('/', $_SERVER['REQUEST_URI'], 3);

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = $repertoire;
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';;
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist'   => 0,
    'dbport'      => '',
    'dbsocket'    => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot   = $pamroot . '/' . $repertoire;
$CFG->dataroot  = $pamdataroot . $repertoire;
$CFG->admin     = 'admin';

$CFG->langlocalroot   = __DIR__ . '\\lang';
$CFG->langotherroot   = __DIR__ . '\\lang';
$CFG->skiplangupgrade = true;
$CFG->lang = 'fr';

$CFG->forced_plugin_settings = [];
$CFG->forced_plugin_settings['auth_entsync'] = [
    'gw'      => 'm33.inst01',
    'inst'    => $repertoire,
    'pamroot' => $pamroot
];

unset($repertoire, $pamdataroot, $pamroot);

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
```

## runcli.php
```php
<?php

//les paramètres sont dans $_SERVER['argv'] 
$parametres = $_SERVER['argv'];

//S'il n'y a pas assez de paramètres => message d'aide
if(count($parametres) < 3)
{
	echo "usage :
runcli.php instance \"script php\" \"options du script\"";
	die();
}

//Le script à lancer est le paramètre n° 2
$script_file = $parametres[2];

//L'instance est le paramètre n°1
//Cela permet de donner la valeur de $repertoire qui sera utilisée dans config.php 
$repertoire = $parametres[1];

//Il faut redéfinir $_SERVER['argv'] comme si le script avait été lancé directement pour qu'il puisse
//retrouver les éventuels paramètres 
$params = array();
$params[0] = $script_file; 
for($i = 3; $i < count($parametres); ++$i) {
	$params[$i-2] = $parametres[$i];
}
$_SERVER['argv'] = $params;

//ces variables ne seront plus utilisées => unset pour éviter qu'elles n'interfèrent avec moodle.
unset($params);
unset($parametres);

//enfin, on appelle le script demandé.
require(dirname(__FILE__).'/'.$script_file);
```