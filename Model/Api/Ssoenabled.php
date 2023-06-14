<?php
namespace Sandipweb\Ssoenabled\Model\Api;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Customer\Model\GroupRegistry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Customer\Model\Customer;

class Ssoenabled
{
     /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    protected $serializer;

    protected $logger;
    /**
     * Token constructor.
     * @param StoreManagerInterface $storeManager
     * @param AccountManagementInterface $accountManagement
     * @param CustomerTokenServiceInterface $customerTokenService
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        CustomerTokenServiceInterface $customerTokenService,
        GroupRegistry $groupRegistry,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        Customer $customer
        )
        {
            $this->customerRepository = $customerRepository;
            $this->groupRegistry = $groupRegistry;
            $this->customerTokenService = $customerTokenService;
            $this->accountManagement = $accountManagement;
            $this->serializer = $serializer;
            $this->storeManager = $storeManager;
            $this->customer = $customer;
        }

    public function sendSsoFlag($username,$password)
    {
        $response = [];
        $jsonResponse = '';
        $customer = '';
        try {
            $googleemailexist = $this->accountManagement->isEmailAvailable($username,1);
            $gengleemailexist = $this->accountManagement->isEmailAvailable($username,4);
            
            if (!$googleemailexist){
                $customer = $this->customerRepository->get($username,1);
            }
            
            if(!$gengleemailexist) {
                $customer = $this->customerRepository->get($username,4);
            }

            if ($customer == '') {
                $response['auth'] = false;
                $response['ssoEnabled'] = false;
                $jsonResponse = $this->serializer->serialize($response);

            } else {
                $groupData = $this->groupRegistry->retrieve($customer->getGroupId());
                $ssoenabled = $groupData->getSsoEnabled();
                if ($ssoenabled && $ssoenabled == 'Yes') {
                    $response['auth'] = false;
                    $response['ssoEnabled'] = true;
                } else {
                    $storeData = $this->storeManager->getStore($customer->getStoreId());
                    $storeCode = (string)$storeData->getCode();
                    $baseurl = $storeData->getBaseUrl();
                    
                    $weburl = $storeData->getWebsiteId() == 4 ? "/l/" : "/google/";
                    $finalurl = substr($baseurl, 0, strpos($baseurl, $weburl));

                    $curl = curl_init();
                    $params = json_encode(["username" => $username,"password" => $password]);
                    $url = $finalurl.'/rest/'.$storeCode.'/V1/integration/customer/token';
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => $params,
                        CURLOPT_HTTPHEADER => array(
                            'Content-Type: application/json'
                    ),
                    ));
    
                    $responseData = curl_exec($curl);

                    $jdecode = json_decode($responseData,true);

                    $response['auth'] = isset($jdecode['message']) ? false : true;
                    
                    $response['ssoEnabled'] = false;
                    curl_close($curl);
                }
                $jsonResponse = $this->serializer->serialize($response);
            }
            return $jsonResponse;
        } catch (\Throwable $th) {
            throw new AuthenticationException(
                __(
                    'The account sign-in was incorrect or your account is disabled temporarily. '
                    . 'Please wait and try again later.'
                )
            );
        }
    }
}