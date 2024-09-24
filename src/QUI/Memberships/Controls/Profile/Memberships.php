<?php

namespace QUI\Memberships\Controls\Profile;

use QUI;
use QUI\FrontendUsers\Controls\Profile\AbstractProfileControl;

class Memberships extends AbstractProfileControl
{
    /**
     * Constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setJavaScriptControl('package/quiqqer/memberships/bin/controls/profile/UserProfile');
    }

    /**
     * Return the inner body of the element
     * Can be overwritten
     *
     * @return string
     * @throws \QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        return $Engine->fetch(dirname(__FILE__) . '/Memberships.html');
    }

    /**
     * Method is called, when on save is triggered
     *
     * @return mixed|void
     */
    public function onSave(): void
    {
    }

    /**
     * Validate the send data
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function validate(): void
    {
    }
}
