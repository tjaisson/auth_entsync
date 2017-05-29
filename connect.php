<?php
list(,$repertoire,$prm) = explode('/',$_SERVER['REQUEST_URI'],3);
if(isset($_GET[ent])) {
    $prm = "?ent={$_GET[ent]}";
} else {
    $prm = '';
}
setcookie('MoodleSession',null,-1,"/{$repertoire}/");
header("location: /{$repertoire}/auth/entsync/login.php{$prm}");
?>
<html><head></head><body>redirection</body></html>