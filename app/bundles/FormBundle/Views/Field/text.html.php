<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$containerType     = (isset($type)) ? $type : 'text';
$defaultInputClass = (isset($inputClass)) ? $inputClass : 'input';

include __DIR__.'/field_helper.php';

$formButtons = (!empty($inForm)) ? $view->render('MauticFormBundle:Builder:actions.html.php',
    array(
        'deleted' => (!empty($deleted)) ? $deleted : false,
        'id'      => $id,
        'formId'  => $formId
    )) : '';


$label = (!$field['showLabel']) ? '' : <<<HTML

                <label $labelAttr>{$view->escape($field['label'])}</label>
HTML;


$help = (empty($helpMessage)) ? '' : <<<HTML

                <span class="mauticform-helpmessage">{$helpMessage}</span>
HTML;

if ($containerType == 'textarea'):
$textInput = <<<HTML

                <textarea $inputAttr>{$field['defaultValue']}</textarea>
HTML;


else:
$textInput = <<<HTML

                <input {$inputAttr} type="$containerType" />
HTML;
endif;


$html = <<<HTML

            <div $containerAttr>{$formButtons}{$label}{$help}{$textInput}
                <span class="mauticform-errormsg" style="display: none;">$validationMessage</span>
            </div>

HTML;

echo $html;