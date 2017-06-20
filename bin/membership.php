<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;

define('QUIQQER_SYSTEM', true);

require dirname(dirname(dirname(dirname(__FILE__)))) . '/header.php';

\QUI\System\Log::writeRecursive($_REQUEST);

/**
 * Send 401 status code if anything goes wrong
 */
function send401()
{
    $Response = QUI::getGlobalResponse();
    $Response->setStatusCode(401, 'Unauthorized or malformed request');
    $Response->send();

    exit;
}

/**
 * Cancel membership
 */
function cancel()
{
    // @todo check if user is logged in?

    $Engine = QUI::getTemplateManager()->getEngine();

    try {
        $MembershipUsers = MembershipUsersHandler::getInstance();
        $MembershipUser  = $MembershipUsers->getChild((int)$_REQUEST['mid']);
        $MembershipUser->confirmCancel($_REQUEST['hash']);
    } catch (QUI\Memberships\Exception $Exception) {
        $Engine->assign(array(
            'error'        => true,
            'errorMessage' => $Exception->getMessage()
        ));
    } catch (\Exception $Exception) {
        send401();
    }

    $template = $Engine->fetch(dirname(__FILE__, 2) . '/templates/cancel_confirm.html');

    echo $template;
    exit;
}

if (empty($_REQUEST['mid'])
    || empty($_REQUEST['hash'])
    || empty($_REQUEST['action'])
) {
    send401();
}

switch ($_REQUEST['action']) {
    case 'confirmcancel':
        cancel();
        break;

    default:
        send401();
}

exit;