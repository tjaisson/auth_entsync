<?php 
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once $CFG->libdir.'/formslib.php';
require_once(__DIR__ . '/lib/table.php');
require_once(__DIR__ . '/lib/cohorthelper.php');
require_once('ent_defs.php');

require_login();
$sitecontext = context_system::instance();
require_capability('moodle/user:viewdetails', $sitecontext);

$profile = required_param('profile', PARAM_INT);
if($profile === 1) {
    $cohort =  required_param('cohort', PARAM_INT);
    $lst = auth_entsync_usertbl::get_users_ent_elev($cohort);
    $ttl = auth_entsync_cohorthelper::get_cohorts()[$cohort];
} else if($profile === 2) {
    $lst = auth_entsync_usertbl::get_users_ent_ens();
    $ttl = "Enseignant";
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>
</title>
        <style type="text/css">
        body {
            background-color: #808080;
            font-family: sans-serif;
        }

        div.etiqus {
            overflow: auto;
            background-color: white;
            width: 50em;
            margin: 0 auto 0;
            padding: 0 0 1em 1em;
        }

        div.etiqu {
            page-break-inside: avoid;
            border: 1px solid;
            border-radius: 10px;
            margin: 1em;
            padding: 1em;
            width: 20em;
            float: left;
            min-height: 1px;
            height: 11em;
			display:inline;
        }

        p {
            font-size: 1.3em;
            margin: 0;
        }
            p.nom {
                height: 3em;
            }
            p.cl {
                padding: 0.2em;
                float: right;
                border: 1px solid;
            }

            p.tt {
                margin-top: 0.5em;
            }

            p.tt, p.nom, p.cl {
                font-weight: bold;
            }

            p.id, p.pw {
                padding-left: 1em;
            }

            p.nt {
                font-size: 0.8em;
            }

        @media print
{    
    div.butt
    {
        display: none !important;
    }
        div.etiqus {
		            overflow: visible !important;
		}
        p.break {
		   page-break-after: always;
		}
}
    </style>
</head>

<body>
    <div class="etiqus">
        <div class="butt"><a href="javascript:window.print()">Imprimer</a> <a href="javascript:window.close()">Fermer</a><hr /></div>
        
<?php
    if(isset($_POST['select'])) {
        $select = $_POST['select'];
    } else {
        $select = false;
    }
        

    $i = 0;
    foreach($lst as $u) {
        if((!$select) || (in_array($u->id, $select)) ) {
            if($u->local === '0') {
                $u->username = 0;
            } else {
                if(!isset($u->password)) $u->password = '&bull;&bull;&bull;&bull;&bull;';
            }
            ?>
                <div class="etiqu">
                    <p class="cl"><?php echo $ttl;?></p>
                    <p class="nom"><?php echo $u->firstname . ' ' . $u->lastname; ?></p>
                <hr />
                <?php if($u->username) { ?>
                    <p class="tt">Codes Moodle</p>
                    <p class="id">id.&nbsp;: <?php echo $u->username; ?></p>
                    <p class="pw">pw&nbsp;: <?php echo $u->password; ?></p>
                <?php } ?>
                </div>
        <?php 
            if(++$i === 8) {
                $i=0;
                echo '<p class="break">&nbsp;</p>';
            }
        }
    } ?>        
    </div>
</body>
</html>



