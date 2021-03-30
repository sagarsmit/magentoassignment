<?php
/**
 * Solwin Infotech
 * Solwin Contact Form Widget Extension
 *
 * @category   Solwin
 * @package    Solwin_Contactwidget
 * @copyright  Copyright © 2006-2020 Solwin (https://www.solwininfotech.com)
 * @license    https://www.solwininfotech.com/magento-extension-license/
 */
namespace Solwin\Contactwidget\Controller\Widget;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Area;

class Index extends \Magento\Framework\App\Action\Action
{

    const EMAIL_TEMPLATE = 'contactwidget_section/emailsend/emailtemplate';
    const EMAIL_SENDER = 'contactwidget_section/emailsend/emailsenderto';
    const XML_PATH_EMAIL_RECIPIENT = 'contactwidget_section/emailsend/emailto';
    const REQUEST_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const REQUEST_RESPONSE = 'g-recaptcha-response';

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var StateInterface
     */
    protected $_inlineTranslation;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Solwin\Contactwidget\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Solwin\Contactwidget\Helper\Data $helper
    ) {
        parent::__construct($context);
        $this->_transportBuilder = $transportBuilder;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_scopeConfig = $scopeConfig;
        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
    }

    public function execute()
    {

        $remoteAddr = filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_SANITIZE_STRING
        );
        $data = $this->getRequest()->getParams();
        $resultRedirect = $this->resultRedirectFactory->create();

        $data['name'] = $this->removeScriptTag($data['name']);
        if ($data['name'] == "") {
            $this->messageManager->addError(__('Name is required field. Please Enter correct value.'));
            $resultRedirect->setPath($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        $data['subject'] = $this->removeScriptTag($data['subject']);
        if ($data['subject'] == "") {
            $this->messageManager->addError(__('Subject is required field. Please Enter correct value.'));
            $resultRedirect->setPath($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        $data['comment'] = $this->removeScriptTag($data['comment']);
        if ($data['comment'] == "") {
            $this->messageManager->addError(__('What’s on your mind? is required field. Please Enter correct value.'));
            $resultRedirect->setPath($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
		
		$data['department'] = $this->removeScriptTag($data['department']);
        if ($data['department'] == "") {
            $this->messageManager->addError(__('Department is required field. Please Select Department.'));
            $resultRedirect->setPath($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        $redirectUrl = $data['currUrl'];
        $secretkey = $this->_helper
                ->getConfigValue(
                    'contactwidget_section/recaptcha/recaptcha_secretkey'
                );
        $sitekey = $this->_helper
                ->getConfigValue(
                    'contactwidget_section/recaptcha/recaptcha_sitekey'
                );
        $enablerecaptcha = $data['enablerecaptcha'];
        if ($enablerecaptcha && ($sitekey == "" || $secretkey == "")) {
            $this->messageManager->addError('Recaptcha enabled but the captcha site key or secret key may be empty. Please contact the store admin for this issue.');
            return $resultRedirect->setUrl($redirectUrl);
        }

        $captchaErrorMsg = $this->_helper
                ->getConfigValue(
                    'contactwidget_section/recaptcha/recaptcha_errormessage'
                );

        if ($data['enablerecaptcha']) {
            if ($captchaErrorMsg == '') {
                $captchaErrorMsg = 'Invalid captcha. Please try again.';
            }
            $captcha = '';
            if (filter_input(INPUT_POST, 'g-recaptcha-response') !== null) {
                $captcha = filter_input(INPUT_POST, 'g-recaptcha-response');
            }

            if (!$captcha) {
                $this->messageManager->addError($captchaErrorMsg);
                return $resultRedirect->setUrl($redirectUrl);
            } else {
                $response = file_get_contents(
                    "https://www.google.com/recaptcha/api/siteverify"
                        . "?secret=" . $secretkey
                        . "&response=" . $captcha
                    . "&remoteip=" . $remoteAddr
                );
                $response = json_decode($response, true);

                if ($response["success"] === false) {
                    $this->messageManager->addError($captchaErrorMsg);
                    return $resultRedirect->setUrl($redirectUrl);
                }
            }
        }

        try {

            $postObject = new \Magento\Framework\DataObject();
            $postObject->setData($data);

            $error = false;

            if (!\Zend_Validate::is(trim($data['name']), 'NotEmpty')) {
                $error = true;
            }

            if (!\Zend_Validate::is(trim($data['email']), 'NotEmpty')) {
                $error = true;
            }

            if (!\Zend_Validate::is(trim($data['subject']), 'NotEmpty')) {
                $error = true;
            }

            if (!\Zend_Validate::is(trim($data['comment']), 'NotEmpty')) {
                $error = true;
            }
			
			if (!\Zend_Validate::is(trim($data['department']), 'NotEmpty')) {
                $error = true;
            }

            if ($error) {
                throw new \Exception();
            }

            // send mail to recipients
            $this->_inlineTranslation->suspend();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $transport = $this->_transportBuilder->setTemplateIdentifier(
                $this->_scopeConfig->getValue(
                    self::EMAIL_TEMPLATE,
                    $storeScope
                )
            )->setTemplateOptions(
                [
                                'area' => Area::AREA_FRONTEND,
                                'store' => $this->_storeManager
                                        ->getStore()
                                        ->getId(),
                            ]
            )->setTemplateVars(['data' => $postObject])
                    ->setFrom($this->_scopeConfig->getValue(
                        self::EMAIL_SENDER,
                        $storeScope
                    ))
                    ->addTo($this->_scopeConfig->getValue(
                        self::XML_PATH_EMAIL_RECIPIENT,
                        $storeScope
                    ))
                    ->getTransport();

            $transport->sendMessage();
            $this->_inlineTranslation->resume();

            $this->messageManager->addSuccess(__('Contact Us request has been '
                    . 'received. We\'ll respond to you very soon.'));
            return $resultRedirect->setUrl($redirectUrl);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (\RuntimeException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $this->_inlineTranslation->resume();
            $this->messageManager->addException($e, __('Something went wrong '
                    . 'while sending the contact us request.'));
        }
		
		//save the contact page data into db
		
		try {
			$data = $this->getRequest()->getParams();
			$name = isset($data['name']) ? $this->removeScriptTag($data['name']):'';
			$email = isset($data['email']) ? $this->removeScriptTag($data['email']):'';
			$telephone = isset($data['telephone']) ? $this->removeScriptTag($data['telephone']):'';
			$subject = isset($data['subject']) ? $this->removeScriptTag($data['subject']):'';
			$comment = isset($data['comment']) ? $this->removeScriptTag($data['comment']):'';
			$department = isset($data['department']) ? $this->removeScriptTag($data['department']):'';
			$file = isset($data['file']) ? $this->removeScriptTag($data['file']):'';
			
			$this->_resources = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\ResourceConnection');
			$connection= $this->_resources->getConnection();

			$contactTable = $this->_resources->getTableName('contact_us');
			$sql = "INSERT INTO " . $contactTable . "(name, email, telephone, subject, comment, department, file) VALUES ('".$name."', '".$email."', '".$telephone."', '".$subject."', '".$comment."', '".$department."', '".$file."')";
			$connection->query($sql);
		} catch (LocalizedException $e) {
			$this->messageManager->addErrorMessage($e->getMessage());
		} catch (\Exception $e) {
			$this->messageManager->addErrorMessage(
				__('An error occurred while saving your form. Please try again later.')
			);
		}
		
        return $resultRedirect->setUrl($redirectUrl);
    }

    public function removeScriptTag($original_string, $replace_string = "")
    {
        return preg_replace("/<\s*script.*?>.*?(<\s*\/script.*?>|$)/is", $replace_string, $original_string);
    }
}
