<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\Application\WebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Yepr\Component\Mdimport\Administrator\Model\ImportModel;

/**
 * The import, one batch per request: `task=import.step&step=<step>&offset=<n>`.
 *
 * Answers in JSON, which media/com_mdimport/js/import.js reads to decide what
 * to ask for next.
 */
final class ImportController extends BaseController
{
    public function step(): void
    {
        $app = $this->app;

        try {
            if (!Session::checkToken()) {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            if (!$app->getIdentity()->authorise('mdimport.import', 'com_mdimport')) {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            // A batch of content saves can outlast a short max_execution_time,
            // and stopping one half way leaves an article saved and its record
            // not - which the next import would then adopt, but still.
            @set_time_limit(300);

            /** @var ImportModel $model */
            $model  = $this->getModel('Import', 'Administrator', ['ignore_request' => true]);
            $result = $model->importer()->run(
                $this->input->getCmd('step', 'download'),
                max(0, $this->input->getInt('offset', 0))
            );

            echo new JsonResponse($result);
        } catch (\Throwable $e) {
            $code = (int) $e->getCode();

            if ($app instanceof WebApplicationInterface) {
                $app->setHeader('status', (string) ($code >= 400 && $code < 600 ? $code : 500), true);
            }

            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }
}
