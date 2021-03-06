<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\BuilderTokenHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\EmailBundle\Helper\PlainTextHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController
 *
 * @package Mautic\EmailBundle\Controller
 */
class AjaxController extends CommonAjaxController
{

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function setBuilderContentAction(Request $request)
    {
        $dataArray = array('success' => 0);
        $entityId  = InputHelper::clean($request->request->get('entity'));
        $session   = $this->factory->getSession();

        if (!empty($entityId)) {
            $sessionVar = 'mautic.emailbuilder.'.$entityId.'.content';

            // Check for an array of slots
            $slots   = InputHelper::_($request->request->get('slots', array(), true), 'html');
            $content = $session->get($sessionVar, array());

            if (!is_array($content)) {
                $content = array();
            }

            if (!empty($slots)) {
                // Builder was closed so save each content
                foreach ($slots as $slot => $newContent) {
                    $content[$slot] = $newContent;
                }

                $session->set($sessionVar, $content);
                $dataArray['success'] = 1;
            } else {
                // Check for a single slot
                $newContent = InputHelper::html($request->request->get('content'));
                $slot       = InputHelper::clean($request->request->get('slot'));

                if (!empty($slot)) {
                    $content[$slot] = $newContent;
                    $session->set($sessionVar, $content);
                    $dataArray['success'] = 1;
                }
            }
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function getAbTestFormAction(Request $request)
    {
        $dataArray = array(
            'success' => 0,
            'html'    => ''
        );
        $type      = InputHelper::clean($request->request->get('abKey'));
        $emailId   = InputHelper::int($request->request->get('emailId'));

        if (!empty($type)) {
            //get the HTML for the form
            /** @var \Mautic\EmailBundle\Model\EmailModel $model */
            $model = $this->factory->getModel('email');

            $email = $model->getEntity($emailId);

            $abTestComponents = $model->getBuilderComponents($email, 'abTestWinnerCriteria');
            $abTestSettings   = $abTestComponents['criteria'];

            if (isset($abTestSettings[$type])) {
                $html     = '';
                $formType = (!empty($abTestSettings[$type]['formType'])) ? $abTestSettings[$type]['formType'] : '';
                if (!empty($formType)) {
                    $formOptions = (!empty($abTestSettings[$type]['formTypeOptions'])) ? $abTestSettings[$type]['formTypeOptions'] : array();
                    $form        = $this->get('form.factory')->create(
                        'email_abtest_settings',
                        array(),
                        array('formType' => $formType, 'formTypeOptions' => $formOptions)
                    );
                    $html        = $this->renderView(
                        'MauticEmailBundle:AbTest:form.html.php',
                        array(
                            'form' => $this->setFormTheme($form, 'MauticEmailBundle:AbTest:form.html.php', 'MauticEmailBundle:FormTheme\Email')
                        )
                    );
                }

                $html                 = str_replace(
                    array(
                        'email_abtest_settings[',
                        'email_abtest_settings_',
                        'email_abtest_settings'
                    ),
                    array(
                        'emailform[variantSettings][',
                        'emailform_variantSettings_',
                        'emailform'
                    ),
                    $html
                );
                $dataArray['html']    = $html;
                $dataArray['success'] = 1;
            }
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function sendBatchAction(Request $request)
    {
        $dataArray = array('success' => 0);

        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model    = $this->factory->getModel('email');
        $objectId = $request->request->get('id', 0);
        $pending  = $request->request->get('pending', 0);
        $limit    = $request->request->get('batchlimit', 100);

        if ($objectId && $entity = $model->getEntity($objectId)) {
            $dataArray['success'] = 1;
            $session              = $this->factory->getSession();
            $progress             = $session->get('mautic.email.send.progress', array(0, (int) $pending));
            $stats                = $session->get('mautic.email.send.stats', array('sent' => 0, 'failed' => 0, 'failedRecipients' => array()));

            if ($pending && !$inProgress = $session->get('mautic.email.send.active', false)) {
                $session->set('mautic.email.send.active', true);
                list($batchSentCount, $batchFailedCount, $batchFailedRecipients) = $model->sendEmailToLists($entity, null, $limit);

                $progress[0] += ($batchSentCount + $batchFailedCount);
                $stats['sent'] += $batchSentCount;
                $stats['failed'] += $batchFailedCount;

                foreach ($batchFailedRecipients as $list => $emails) {
                    $stats['failedRecipients'] = $stats['failedRecipients'] + $emails;
                }

                $session->set('mautic.email.send.progress', $progress);
                $session->set('mautic.email.send.stats', $stats);
                $session->set('mautic.email.send.active', false);
            }

            $dataArray['percent'] = ($progress[1]) ? ceil(($progress[0] / $progress[1]) * 100) : 100;

            $dataArray['progress'] = $progress;
            $dataArray['stats']    = $stats;
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * Called by parent::getBuilderTokensAction()
     *
     * @param $query
     *
     * @return array
     */
    protected function getBuilderTokens($query)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model = $this->factory->getModel('email');

        return $model->getBuilderComponents(null, array('tokens', 'visualTokens'), $query);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function generatePlaintTextAction(Request $request)
    {
        $dataArray = array();
        $mode      = $request->request->get('mode');
        $custom    = $request->request->get('custom');
        $id        = $request->request->get('id');

        $parser = new PlainTextHelper(
            array(
                'base_url' => $request->getSchemeAndHttpHost().$request->getBasePath()
            )
        );

        if ($mode == 'custom') {
            // Convert placeholders into raw tokens
            BuilderTokenHelper::replaceVisualPlaceholdersWithTokens($custom);

            $dataArray['text'] = $parser->setHtml($custom)->getText();
        } else {
            $session     = $this->factory->getSession();
            $contentName = 'mautic.emailbuilder.'.$id.'.content';

            $content = $session->get($contentName, array());
            if (strpos($id, 'new') === false) {
                $entity          = $this->factory->getModel('email')->getEntity($id);
                $existingContent = $entity->getContent();
                $content         = array_merge($existingContent, $content);
            }

            // Convert placeholders into raw tokens
            BuilderTokenHelper::replaceVisualPlaceholdersWithTokens($content);

            $parsed = array();
            foreach ($content as $html) {
                $parsed[] = $parser->setHtml($html)->getText();
            }

            $dataArray['text'] = implode("\n\n", $parsed);
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function updateStatsChartAction(Request $request)
    {
        $emailId         = InputHelper::int($request->request->get('emailId'));
        $emailType       = InputHelper::clean($request->request->get('emailType'));
        $includeVariants = InputHelper::boolean($request->request->get('includeVariants', false));
        $amount          = InputHelper::int($request->request->get('amount'));
        $unit            = InputHelper::clean($request->request->get('unit'));
        $dataArray       = array('success' => 0);

        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model           = $this->factory->getModel('email');

        $dataArray['stats']   = ($emailType == 'template') ? $model->getEmailGeneralStats($emailId, $includeVariants, $amount, $unit) :
            $model->getEmailListStats($emailId, $includeVariants);
        $dataArray['success'] = 1;

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function getAttachmentsSizeAction(Request $request)
    {
        $assets = $request->get('assets', array(), true);
        $size   = 0;
        if ($assets) {
            /** @var \Mautic\AssetBundle\Model\AssetModel $assetModel */
            $assetModel = $this->factory->getModel('asset');
            $size       = $assetModel->getTotalFilesize($assets);
        }

        return $this->sendJsonResponse(array('size' => $size));
    }
}