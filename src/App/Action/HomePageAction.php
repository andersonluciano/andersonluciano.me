<?php

namespace App\Action;

use GuzzleHttp\Client;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Router;
use Zend\Expressive\Template;


class HomePageAction implements ServerMiddlewareInterface
{
    private $router;

    private $template;

    private $telegramHornKey;

    public function __construct(Router\RouterInterface $router, Template\TemplateRendererInterface $template = null)
    {
        $this->router = $router;
        $this->template = $template;
        $this->telegramHornKey = "";
        $this->recaptchaKey = "";
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {

        $params = array();
        try {


            if ($request->getMethod() == "POST") {
                $params = $request->getParsedBody();
                if ($params['g-recaptcha-response'] == "") {
                    $params['exception'] = "Please check captcha";
                }


                $client = new Client();
                $captchaVerify = $client->post("https://www.google.com/recaptcha/api/siteverify", [
                    "form_params" => [
                        "secret" => $this->recaptchaKey,
                        "response" => $params['g-recaptcha-response']
                    ],
                    'http_errors' => false
                ])->getBody()->getContents();
                $captchaVerify = json_decode($captchaVerify, true);
                if ($captchaVerify['success'] != true) {
                    $params['exception'] = "Please check captcha";
                }

                if (!array_key_exists("exception", $params)) {
                    $message = "Message from profile site: '" . $params['name'] . "'' of email address'" . $params['email'] . "' said: " . $params['message'] . " - at " . date("d-m-Y H:i:s");

                    $response = $client->post("https://integram.org/" . $this->telegramHornKey, [
                        "json" => [
                            "text" => $message
                        ],
                        'http_errors' => false
                    ]);

                    if ($response->getStatusCode() != 200) {
                        file_put_contents("/tmp/messages-unsent.txt", $message . "\n\n", FILE_APPEND);
                    }

                    $params = array("messageResponse" => "Your message has been sent!");
                }
            }
        } catch (\Exception $e) {

        }

        return new HtmlResponse($this->template->render('app::home-page', array("params" => $params)));
    }
}
