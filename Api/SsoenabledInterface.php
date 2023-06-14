<?php
namespace Sandipweb\Ssoenabled\Api;

interface SsoenabledInterface
{
    /**
     * Create access token for admin given the customer credentials.
     *
     * @param string $username
     * @param string $password
     * @return string Token created
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
 
    public function sendSsoFlag($username,$password);

}