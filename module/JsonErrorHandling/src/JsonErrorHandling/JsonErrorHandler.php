<?php

namespace JsonErrorHandling;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Header\Accept;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ModelInterface;

final class JsonErrorHandler extends AbstractListenerAggregate
{
    /**
     * @var bool
     */
    private $displayExceptions;

    public function __construct(bool $displayExceptions)
    {
        $this->displayExceptions = $displayExceptions;
    }

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $events->attach(
            MvcEvent::EVENT_RENDER,
            function (MvcEvent $event) : void {
                // this closure is just because of the lack of consistent callable usage in PHP
                $this->onError($event);
            }
        );
    }

    /**
     * Note: this code comes straight from
     * @link https://akrabat.com/returning-json-errors-in-a-zf2-application/
     *
     * It wasn't tested via unit testing: that needs to happen before using it.
     *
     * This is just an example. Test.Your.Shit.
     *
     * Alternatively, you can use
     * @link https://apigility.org/documentation/modules/zf-api-problem
     *
     * @param MvcEvent $error
     */
    private function onError(MvcEvent $error) : void
    {
        if (!$error->isError()) {
            return;
        }

        $currentModel = $error->getResult();

        if ($currentModel instanceof JsonModel) {
            return;
        }

        if (! $this->acceptHeaderMatches($error)) {
            return;
        }

        // use application/api-problem+json fields.
        $response = $error->getResponse();

        if (! $response instanceof Response) {
            return;
        }

        $model = new JsonModel(array(
            'httpStatus' => $response->getStatusCode(),
            'title'      => $response->getReasonPhrase(),
            'detail'     => $this->getExceptionMessage($error) ?? $this->getDetailReason($error),
        ));

        if ($messages = $this->getExceptionMessages($error)) {
            $model->setVariable('messages', $messages);
        }

        $model->setTerminal(true);
        $error->setResult($model);
        $error->setViewModel($model);
    }

    private function getExceptionMessage(MvcEvent $event) : ?string
    {
        $exception = $this->getException($event);

        if (! $exception) {
            return null;
        }

        return $exception->getMessage();
    }

    private function getExceptionMessages(MvcEvent $event) : ?array
    {
        $exception = $this->getException($event);

        if (! $exception) {
            return null;
        }

        $messages = [];

        do {
            // @TODO this can be improved to include stack traces, files, etc
            $messages[] = $exception->getMessage() . "\n\n" . $exception->__toString();
        } while ($exception = $exception->getPrevious());

        return $messages;
    }

    private function getException(MvcEvent $event) : ?\Throwable
    {
        if (! $this->displayExceptions) {
            return null;
        }

        $currentModel = $event->getResult();

        if (! $currentModel instanceof ModelInterface) {
            return null;
        }

        $exception = $currentModel->getVariable('exception');

        if (! $exception instanceof \Throwable) {
            return null;
        }

        return $exception;
    }

    private function getDetailReason(MvcEvent $event) : string
    {
        $currentModel = $event->getResult();

        if (! $currentModel instanceof ModelInterface || ! $currentModel->getVariable('reason')) {
            return 'No reason provided';
        }

        $reasons = [
            Application::ERROR_CONTROLLER_CANNOT_DISPATCH => 'The requested controller was unable to dispatch the request.',
            Application::ERROR_CONTROLLER_NOT_FOUND => 'The requested controller could not be mapped to an existing controller class.',
            Application::ERROR_CONTROLLER_INVALID => 'The requested controller was not dispatchable.',
            Application::ERROR_ROUTER_NO_MATCH =>  'The requested URL could not be matched by routing.',
        ];

        return $reasons[$currentModel->getVariable('reason', '')]
            ?? $currentModel->getVariable('message')
            ?? 'No reason provided';
    }

    private function acceptHeaderMatches(MvcEvent $event) : bool
    {
        $request = $event->getRequest();

        if (! $request instanceof Request) {
            return false;
        }

        $headers = $request->getHeaders();

        if (!$headers->has('Accept')) {
            return false;
        }

        $accept = $headers->get('Accept');

        if (! $accept instanceof Accept) {
            return false;
        }

        $match  = $accept->match('application/json');

        // note: we don't want wildcard matching, as that usually means "give me HTML"
        return $match && '*/*' !== $match->getTypeString();
    }
}
