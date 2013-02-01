<?php
include_once('../../config.php');
include_once('lib.php');

$hd = helpdesk::get_helpdesk();
if (!method_exists($hd, 'email_update')) {
    echo 'Mailer function does not exist.';
    die;
}
$rval = $hd->email_update($hd->get_ticket('1'));
if ($rval === false) {
    echo 'Error sending email. (Bad)';
} else {
    echo 'Email sent or blocked. (Good)';
}
?>
