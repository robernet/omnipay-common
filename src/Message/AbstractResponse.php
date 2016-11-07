<?php
/**
 * Abstract Response
 */

namespace League\Omnipay\Common\Message;

use League\Omnipay\Common\AmountInterface;
use League\Omnipay\Common\Exception\RuntimeException;
use League\Omnipay\Common\Http\Factory;
use Psr\Http\Message\ResponseInterface as HttpResponseInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Abstract Response
 *
 * This abstract class implements ResponseInterface and defines a basic
 * set of functions that all Omnipay Requests are intended to include.
 *
 * Objects of this class or a subclass are usually created in the Request
 * object (subclass of AbstractRequest) as the return parameters from the
 * send() function.
 *
 * Example -- validating and sending a request:
 *
 * <code>
 *   $myResponse = $myRequest->send();
 *   // now do something with the $myResponse object, test for success, etc.
 * </code>
 *
 * @see ResponseInterface
 */
abstract class AbstractResponse implements ResponseInterface
{

    /**
     * The embodied request object.
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * The data contained in the response.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Constructor
     *
     * @param RequestInterface $request the initiating request.
     * @param mixed $data
     */
    public function __construct(RequestInterface $request, $data)
    {
        $this->request = $request;
        $this->data = $data;
    }

    /**
     * Get the initiating request object.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Is the response completed?
     *
     * @return boolean
     */
    abstract public function isCompleted();

    /**
     * Get the response status
     *
     * @return string
     */
    public function getStatus()
    {
        return ResponseInterface::STATUS_UNDEFINED;
    }

    /**
     * Is the response pending?
     *
     * @return boolean
     */
    public function isPending()
    {
        return $this->getStatus() === ResponseInterface::STATUS_PENDING;
    }

    /**
     * Does the response require a redirect?
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return false;
    }

    /**
     * Is the response a transparent redirect?
     *
     * @return boolean
     */
    public function isTransparentRedirect()
    {
        return false;
    }

    /**
     * Is the transaction cancelled by the user?
     *
     * @return boolean
     */
    public function isCancelled()
    {
        return $this->getStatus() === ResponseInterface::STATUS_CANCELLED;
    }

    /**
     * Get the amount of the transaction if returned by the Gateway
     *
     * @return null|AmountInterface
     */
    public function getAmount()
    {
        return null;
    }

    /**
     * Get the response data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Response Message
     *
     * @return null|string A response message from the payment gateway
     */
    public function getMessage()
    {
        return null;
    }

    /**
     * Response code
     *
     * @return null|string A response code from the payment gateway
     */
    public function getCode()
    {
        return null;
    }

    /**
     * Gateway Reference
     *
     * @return null|string A reference provided by the gateway to represent this transaction
     */
    public function getTransactionReference()
    {
        return null;
    }

    /**
     * Get the transaction ID as generated by the merchant website.
     *
     * @return string
     */
    public function getTransactionId()
    {
        return null;
    }

    /**
     * Automatically perform any required redirect
     *
     * This method is meant to be a helper for simple scenarios. If you want to customize the
     * redirection page, just call the getRedirectUrl() and getRedirectData() methods directly.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public function redirect()
    {
        $this->getRedirectResponse()->send();
        exit;
    }

    /**
     * @return HttpResponseInterface
     */
    public function getRedirectResponse()
    {
        if (!$this instanceof RedirectResponseInterface || !$this->isRedirect()) {
            throw new RuntimeException('This response does not support redirection.');
        }

        /** @var $this RedirectResponseInterface */
        if ('GET' === $this->getRedirectMethod()) {
            return Factory::createResponse(302, [
                'location' => $this->getRedirectUrl(),
            ]);
        } elseif ('POST' === $this->getRedirectMethod()) {
            $hiddenFields = '';
            foreach ($this->getRedirectData() as $key => $value) {
                $hiddenFields .= sprintf(
                    '<input type="hidden" name="%1$s" value="%2$s" />',
                    htmlentities($key, ENT_QUOTES, 'UTF-8', false),
                    htmlentities($value, ENT_QUOTES, 'UTF-8', false)
                )."\n";
            }

            $output = '<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Redirecting...</title>
    </head>
    <body onload="document.forms[0].submit();">
        <form action="%1$s" method="post">
            <p>Redirecting to payment page...</p>
            <p>
                %2$s
                <input type="submit" value="Continue" />
            </p>
        </form>
    </body>
</html>';
            $output = sprintf(
                $output,
                htmlentities($this->getRedirectUrl(), ENT_QUOTES, 'UTF-8', false),
                $hiddenFields
            );

            return Factory::createResponse(200, [
                'content-type' => 'text/html',
            ], $output);
        }

        throw new RuntimeException('Invalid redirect method "'.$this->getRedirectMethod().'".');
    }
}
