<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<div class="panel-body">
    <?php echo $view->render('MauticLeadBundle:Social/Facebook:profile.html.php', array(
        'lead'      => $lead,
        'profile'   => $details['profile']
    )); ?>
</div>