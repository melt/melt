<?php

/**
*@desc Application specific controller.
*/
class AppController extends Controller {
    public $authTimeout;
    public $authLevel;
    public $authUsername;
    public $authUserID;
    public $authLastUsernameAttempt;

    // Default layout is HTML in this application.
    public $layout = "html";

    function beforeFilter() {
        // Run authorization for user/permission management.
        AuthorizationComponent::runAuthorization();
        // Get authorization parameters.
        list($this->authTimeout,
             $this->authLevel,
             $this->authUsername,
             $this->authUserID,
             $this->authLastUsernameAttempt) = AuthorizationComponent::readAuthorization();
    }

    function logout() {
        AuthorizationComponent::doLogout();
        api_navigation::redirect(api_navigation::make_local_url("/"));
    }
}

?>
