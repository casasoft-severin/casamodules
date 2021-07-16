<?php
namespace CasaLogService\Service;

use Laminas\View\Model\ViewModel;

use Laminas\Http\Client as HttpClient;
use Laminas\Json\Json;

use Laminas\Config\Writer;

class LogService implements \Laminas\Log\Writer\WriterInterface {
    protected $config = array();
    protected $stack = array();

    public function __construct(){
    }

    public function setConfig($config){
        $this->config = $config;
        if ($config['shutdown']['activate']) {
            $this->activateShutdown();
        }

    }

    public function activateShutdown(){
        register_shutdown_function(array($this, 'emergencyReport'));
    }

    protected $priorities = array(
        'EMERG'   => 0,  // Emergency: system is unusable
        'ALERT'   => 1,  // Alert: action must be taken immediately
        'CRIT'    => 2,  // Critical: critical conditions
        'ERR'     => 3,  // Error: error conditions
        'WARN'    => 4,  // Warning: warning conditions
        'NOTICE'  => 5,  // Notice: normal but significant condition
        'INFO'    => 6,  // Informational: informational messages
        'DEBUG '  => 7,  // Debug: debug messages
    );

    /*
        Puts message into stack to be reported later
    */
    public function stageMsg($message, $priority = 7){
        $this->stack[]['create'] = array(
                'software' => $this->config['software'],
                'message' => $message,
                'priority' => $priority,
                'priorityName' => array_search($priority, $this->priorities),
                'timestamp' => date('Y-m-dTH:i:s',time())
        );
    }

    /*
        Puts php error message into stack to be reported later
    */
    public function stageErr($error){
        $priority = 3;
        switch ($error['type']) {
            case E_ERROR:           $priority = 3; break;
            case E_WARNING:         $priority = 4; break;
            case E_PARSE:           $priority = 3; break;
            case E_NOTICE:          $priority = 5; break;
            case E_CORE_ERROR:      $priority = 3; break;
            case E_CORE_WARNING:    $priority = 4; break;
            case E_COMPILE_ERROR:   $priority = 3; break;
            case E_COMPILE_WARNING: $priority = 4; break;
            case E_USER_ERROR:      $priority = 3; break;
            case E_USER_WARNING:    $priority = 4; break;
            case E_USER_NOTICE:     $priority = 5; break;
            case E_STRICT:          $priority = 6; break;
            case E_RECOVERABLE_ERROR:$priority = 4; break;
            case E_DEPRECATED:      $priority = 6; break;
            case E_USER_DEPRECATED: $priority = 6; break;
            case E_ALL:             $priority = 4; break;
        }
        $this->stack[]['create'] = array(
            'software' => $this->config['software'],
            'message' => 'PHP-ERROR-'. $error['type'] . ': '.$error['message'] . ' [file:' . $error['file'] . '] [Line:' . $error['line'] . ']' . '[Request: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?' ) . ']',
            'priority' => $priority,
            'priorityName' => array_search($priority, $this->priorities),
            'timestamp' => date('Y-m-dTH:i:s',time())
        );
    }

    /*
        Puts php exception message into stack to be reported later
    */
    public function stageException($e, $priority = 4){
        $this->stack[]['create'] = array(
                'software' => $this->config['software'],
                'message' => 'PHP-EXCEPTION-'. $e->getCode() . ': '.$e->getMessage() . ' [file:' . $e->getFile() . '] [Line:' . $e->getLine() . ']' . '[Request: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?' ) . ']',
                'priority' => $priority,
                'priorityName' => array_search($priority, $this->priorities),
                'timestamp' => date('Y-m-dTH:i:s',time())
        );
    }

    /*
        Sends current stack to API as bulk
    */
    public function report(){
        if (!$this->stack) {
            return false;
        }

        //slack reporting
        if ($this->config['slack_hook']) {
            if (class_exists('cURL')) {
                $priotocolor = array(
                  'EMERG'  => '#D50200',
                  'ALERT'  => '#D50200',
                  'CRIT'   => '#D50200',
                  'ERR'    => '#DF9E30',
                  'WARN'   => '#DF9E30',
                  'NOTICE' => '#27D7E5',
                  'INFO'   => '#30A44F',
                  'DEBUG' => '#444444',
                );
                foreach ($this->stack as $message) {
                  $request = new \cURL\Request($this->config['slack_hook']);
                  $request->getOptions()
                      ->set(CURLOPT_TIMEOUT, 5)
                      ->set(CURLOPT_RETURNTRANSFER, true)
                      ->set(CURLOPT_POST, 1)
                      ->set(CURLOPT_POSTFIELDS, json_encode(
                        array(
                            //'text' => $message['message'],
                            //"icon_emoji" => ":ghost:"
                            "attachments" => array(
                              array(
                                "color" => $priotocolor[$message['create']['priorityName']],
                                "text" => $message['create']['priorityName'] . ': ' . $message['create']['message']
                              )
                            )
                        )))
                      ->set(CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                  $request->addListener('complete', function (\cURL\Event $event) {
                    //$response = $event->response;
                  });

                  while ($request->socketPerform()) {
                      // do anything else when the requests are processed
                      $request->socketSelect();
                      // line below pauses execution until there's new data on socket
                  }
                }
            } else {
                $this->stageMsg('curl not installed, can\' report to slack.', 3);
            }
        }


        $config = array(
            'adapter'   => 'Laminas\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_TIMEOUT_MS => 6000
            ),
        );
        $uri = $this->config['url'] . '/bulk/msg';
        $client = new HttpClient($uri, $config);
        $client->setHeaders(array(
            'Accept' => 'application/json; charset=UTF-8',
            'Content-Type' => 'application/json'
        ));

        $client->setMethod('POST');

        $client->setRawBody(Json::encode($this->stack));
        $client->setEncType(HttpClient::ENC_FORMDATA);
        $client->setAuth($this->config['username'], $this->config['password'], \Laminas\Http\Client::AUTH_BASIC);

        try {
            $response = $client->send();
            $this->stack = array();
            return $response;
        } catch (\Exception $e) {
            return false;
            //probably timeout thats ok ^^;
        }
    }

    /*
        Logs a single message instantly
    */
    public function logMsg($message, $priority = 7){
        $config = array(
            'adapter'   => 'Laminas\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_FRESH_CONNECT => true,
                //CURLOPT_TIMEOUT_MS => 1
            ),
        );
        $query = array();
        $uri = $this->config['url'] . '/msg?' . http_build_query($query);
        $client = new HttpClient($uri, $config);
        $client->setHeaders(array(
            'Accept' => 'application/json; charset=UTF-8',
            'Content-Type' => 'application/json'
        ));

        $client->setMethod('POST');

        $client->setRawBody(Json::encode(array(
            'software' => $this->config['software'],
            'message' => $message,
            'priority' => $priority,
            'priorityName' => array_search($priority, $this->priorities),
            'timestamp' => date('Y-m-dTH:i:s',time())
        )));
        $client->setEncType(HttpClient::ENC_FORMDATA);
        $client->setAuth($this->config['username'], $this->config['password'], \Laminas\Http\Client::AUTH_BASIC);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            //probably timeout thats ok ^^;
        }

        return true;

    }

    /*
        Gets called on script shut-down
    */
    public function emergencyReport(){
        if ($this->config['shutdown']['report_rogue_entries'] && $this->stack) {
            $this->stageMsg('Application has shut down before reporting all entries. ' . count($this->stack) . ' have been auto-reported.', 4);
        }

        $error = error_get_last();
        if(!empty($error) && in_array($error['type'], $this->config['shutdown']['report_error_codes'])){
            $this->stageErr($error);
        };

        if ($this->stack) {
            $this->report();
        }

        return true;
    }

     /**
     * Add a log filter to the writer
     *
     * @param  int|string|Filter $filter
     * @return WriterInterface
     */
    public function addFilter($filter){

    }

    /**
     * Set a message formatter for the writer
     *
     * @param string|Formatter $formatter
     * @return WriterInterface
     */
    public function setFormatter($formatter){

    }

    /**
     * Write a log message
     *
     * @param  array $event
     * @return WriterInterface
     */
    public function write(array $event){
        if (!$this->config['zend_logger_cap'] || $event['priority'] <= $this->config['zend_logger_cap']) {
            $this->stageMsg($event['message'], $event['priority']);
        }
    }

    /**
     * Perform shutdown activities
     *
     * @return void
     */
    public function shutdown(){
        $this->report();
    }



}
