<?php defined('MOODLE_INTERNAL') || die; ?>
<p>La mise en place du connecteur avec Educ'Horus ne nécessite pas de paramétrage particulier côté Educ'Horus. Il suffit d'activer
le connecteur Educ'Horus dans Moodle, de renseigner les paramètres d'URL de votre espace Educ'Horus, puis d'importer les utilisateurs dans "Importation des utilisateurs".</p>
<p>Les élèves et les enseignants accèderont ainsi à Moodle avec leurs codes d'accès Educ'Horus.</p>
<p>Vous pouvez créer un signet dans Educ'Horus qui pointe vers l'URL du connecteur&nbsp;:</p>
<p style="text-align: center;"><?php echo $this->get_connector_url(); ?></p>
