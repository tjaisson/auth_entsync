<?php defined('MOODLE_INTERNAL') || die(); ?>
<p>La mise en place du connecteur avec l'ENT Paris Classe Numérique ne nécessite pas de paramétrage particulier côté ENT. Il suffit d'activer
le connecteur PCN dans Moodle puis d'importer les utilisateurs dans "Importation des utilisateurs".</p>
<p>Les élèves et les enseignants accèderont ainsi à Moodle avec leurs codes d'accès PCN.</p>
<p>Vous pouvez créer un signet dans PCN qui pointe vers l'URL du connecteur&nbsp;:</p>
<p style="text-align: center;"><?php echo $this->get_connector_url(); ?></p>
