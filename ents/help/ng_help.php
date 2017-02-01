<?php defined('MOODLE_INTERNAL') || die;
global $CFG;
?>

<p>La mise en place du connecteur avec Open ENT NG nécessite la création d'une Application côté ENT.
Cela se fait dans la <b>console d'administration</b>&nbsp;:</p>
<p><img style = "border: 1px solid;" src='./ents/help/ng2.jpg' /></p>
<ol>
<li>Sélectionner "Applications",</li>
<li>Cliquer sur "Connecteurs&nbsp;&&nbsp;liens",</li>
<li>Cliquer sur le bouton "+",</li>
</ol>
<p><img style = "border: 1px solid;" src='./ents/help/ng3.jpg' /></p>
<ol start="4">
<li>Renseigner le formulaire "Paramètres du lien"&nbsp;:
<ul>
<li>Identifiant&nbsp;: Moodle PAM</li>
<li>Nom d'affichage&nbsp;: Moodle</li>
<li>Icône&nbsp;: <?php echo "{$CFG->wwwroot}/auth/entsync/ents/help/moodle-ng.png"; ?></li>
<li>URL&nbsp;: <?php echo $this->get_connector_url(); ?></li>
<li>Cible&nbsp;: Nouvelle page</li>
</ul> 
</li>
<li>Cliquer sur "Afficher les paramètres du connecteur" puis cocher "Champs spécifiques CAS",</li>
<li>Cliquer sur "Créer".</li>
</ol>
<p><img style = "border: 1px solid;" src='./ents/help/ng4.jpg' /></p>
<ol start="7">
<li>Cliquer sur "Droits d'accès" du connecteur nouvellement créé puis cocher les groupes d'utilisateurs qui auront accès à Moodle.</li>
</ol>
<p>Vous devez ensuite importer les utilisateurs dans "Importation des utilisateurs".</p>
<p>Les élèves et les enseignants accèderont ainsi à Moodle avec leurs codes d'accès Open ENT NG.</p>
