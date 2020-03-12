<?php
/**
 * Dummy container to represent Http Methods in a standard way.
 * All methods in https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods are listed, in the same order.
 */
abstract class HttpMethod {
    /**
     * The GET method requests a representation of the specified resource.
     * Requests using GET should only retrieve data.
     * 
     * @var string
     */
    const GET = 'GET';
    /**
     * The HEAD method asks for a response identical to that of a GET request, but without the response body.
     * 
     * @var string
     */
    const HEAD = 'HEAD';
    /**
     * The POST method is used to submit an entity to the specified resource, often causing a change in state or side
     * effects on the server.
     * 
     * @var string
     */
    const POST = 'POST';
    /**
     * The PUT method replaces all current representations of the target resource with the request payload.
     * 
     * @var string
     */
    const PUT = 'PUT';
    /**
     * The DELETE method deletes the specified resource.
     * 
     * @var string
     */
    const DELETE = 'DELETE';
    /**
     * The CONNECT method establishes a tunnel to the server identified by the target resource.
     * 
     * @var string
     */
    const CONNECT = 'CONNECT';
    /**
     * The DELETE method deletes the specified resource.
     * 
     * @var string
     */
    const OPTIONS = 'OPTIONS';
    /**
     * The TRACE method performs a message loop-back test along the path to the target resource.
     * 
     * @var string
     */
    const TRACE = 'TRACE';
    /**
     * The PATCH method is used to apply partial modifications to a resource.
     * 
     * @var string
     */
    const PATCH = 'PATCH';
}

/**
 * Encapsulates the logic to do Http[s] requests using Streams native extension, exposing the options supported in
 * HTTP context (https://www.php.net/manual/en/context.http.php) as public properties.
 * 
 * Each object may be reused several times.
 */
class HttpRequest {
    // HTTP Context options
    /**
     * @var string Protocol used to make requests through Streams native extension.
     */
    const STREAM_PROTOCOL = 'http';

    /**
     * @var string GET, POST, or any other HTTP method supported by the remote server.
     * Defaults to GET.
     */
    public $method;
    /**
     * @var array Additional headers to be sent during request. Values in this option will override other values
     * (such as User-agent:, Host:, and Authentication:).
     */
    public $header;
    /**
     * @var string Value to send with User-Agent: header. This value will only be used if user-agent is not specified
     * in the header context option above.
     * 
     * By default the user_agent php.ini setting is used.
     */
    public $user_agent;
    /**
     * @var string Additional data to be sent after the headers. Typically used with POST or PUT requests.
     */
    public $content;
    /**
     * @var string URI specifying address of proxy server. (e.g. tcp://proxy.example.com:5100).
     */
    public $proxy;
    /**
     * @var bool When set to TRUE, the entire URI will be used when constructing the request. (i.e. GET
     * http://www.example.com/path/to/file.html HTTP/1.0). While this is a non-standard request format, some proxy
     * servers require it.
     * 
     * Defaults to FALSE.
     */
    public $request_fulluri;
    /**
     * @var integer Follow Location header redirects. Set to 0 to disable.
     * 
     * Defaults to 1.
     */
    public $follow_location;
    /**
     * @var integer The max number of redirects to follow. Value 1 or less means that no redirects are followed.
     * 
     * Defaults to 20.
     */
    public $max_redirects;
    /**
     * @var float HTTP protocol version.
     * 
     * Defaults to 1.0.
     */
    public $protocol_version;
    /**
     * @var float Read timeout in seconds, specified by a float (e.g. 10.5).
     * 
     * By default the default_socket_timeout php.ini setting is used.
     */
    public $timeout;
    /**
     * @var bool Fetch the content even on failure status codes.
     * 
     * Defaults to FALSE.
     */
    public $ignore_errors;



    // Request options
    /**
     * @var string URL to call on the request.
     */
    public $url;
    


    /**
     * Creates a new HttpResponse instance, that may be reused several times.
     * 
     * @param array $Options Allows to initialize any to every field on this object, using the array's keys as
     * property names
     * @return self
     */
    public function __construct($Options = array())
    {
        if (is_array($Options)) {
            foreach ($Options as $Name => $Value) {
                if (property_exists($this, $Name)) {
                    $this->{$Name} = $Value;
                }
            }
        }
    }
    /**
     * Adds the given $Headers to the request, ovewriting previous headers with the same name.
     * 
     * @param string|string[] $Headers
     * @return self
     */
    public function AddHeaders($Headers) {
        if(!is_array($Headers)) {
            $Headers = array($Headers);
        }

        if (empty($this->header)) {
            $this->header = array();
        }
        
        foreach ($Headers as $Header => $Value) {
            if(is_string($Header)) {
                $this->header[$Header] = $Value;
            } else {
                $Test = explode( ':', $Value, 2 );
                if (isset( $Test[1] )) {
                    $this->header[$Test[0]] = $Test[1];
                } else {
                    $this->header[] = $Value;
                }
            }
        }

        return $this;
    }

    /**
     * Internal method to open a stream for this HttpRequest, creating a HttpResponse object to deal with the
     * [meta]data and allow Javascript's Fetch-like usage.
     * 
     * @return HttpResponse
     */
    protected function Execute() {
        $Config = array(
            'method' => empty($this->method) ? HttpMethod::GET : strtoupper($this->method)
        );

        $Options = array(
            'header', 'content', 'proxy', 'request_fulluri', 'follow_location',
            'max_redirects', 'protocol_version', 'timeout', 'ignore_errors'
        );
        foreach ($Options as $Option) {
            if (!empty($this->{$Option})) {
                switch ($Option) {
                    case 'header':
                        $Config['header'] = array();
                        foreach ($this->header as $Header => $Value) {
                            $Config['header'][] = is_string($Header)
                                ? "$Header: $Value"
                                : $Value
                            ;
                        }
                        break;
                    case 'content':
                        if (is_string($this->content)) {
                            $Config['content'] = $this->content;
                        } else {
                            $Config['content'] = http_build_query($this->content);
                        }
                        break;
                    default:
                        if (!empty($this->{$Option})) {
                            $Config[$Option] = $this->{$Option};
                        }
                        break;
                }
            }
        }

        // Creating HTTP Wrapper
        $Context = stream_context_create(array(
            self::STREAM_PROTOCOL => $Config
        ));
        // Generating response with stream
        return new HttpResponse(@fopen($this->url, 'rb', false, $Context), $this);
    }
    /**
     * Removes the given $Headers from Request.
     * 
     * @param string|string[] $Headers
     * @return self
     */
    public function RemoveHeaders($Headers) {
        if (!empty($this->header)) {
            if(!is_array($Headers)) {
                $Headers = array($Headers);
            }
            foreach ($Headers as $Header) {
                $Test = explode( ':', $Header, 2 );
                if (isset( $Test[1] )) {
                    if (array_key_exists($Test[0], $this->header)) {
                        unset($this->header[$Header]);
                    } else {
                        $Index = array_search($Header, $this->header);
                        if ($Index !== false) {
                            array_splice($this->header, $Index, 1);
                        }
                    }
                } else {
                    unset($this->header[$Header]);
                }    
            }
        }
    }


    // Javascript's fetch imitation
    /**
     * Imitation of Javasctript's fetch method, without url parameter (already on the class).
     * Currently suports this fetch options on init: method, headers, body, redirect.
     * 
     * @param array $init
     * @return HttpResponse
     */
    public function _fetch($init = array())
    {
        foreach ($init as $Key => $Value) {
            switch ($Key) {
                case 'method':
                    // Method will be alreay passed to uppercase on .Execute()
                    $this->method = $Value;
                    break;
                case 'headers':
                    $this->AddHeaders($Value);
                    break;
                case 'body':
                    $this->content = $Value;
                    break;
                case 'redirect':
                    $this->follow_location = strtolower($Value) == 'manual'
                        ? 0
                        : 1
                    ;
                    break;
            }
        }
        $this->ignore_errors = true;

        return $this->Execute();
    }

    /**
     * Imitation of Javasctript's fetch method, with PHP-equivalent syntax.
     * Currently suports this fetch options on init: method, headers, body, redirect.
     * 
     * @param string $resource
     * @param array $init
     * @return HttpResponse
     */
    public static function fetch($resource, $init = array())
    {
        return (new HttpRequest(array('url' => $resource)))->_fetch($init);
    }



    // jQuery's ajax imitation
    /**
     * Makes a GET request in a similar fashion to jQuery, but supressing $url and $data (suposed to have been already
     * set before calling this method).
     * 
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return HttpResponse
     */
    public function _get($success = null, $dataType = null)
    {
        if (!empty($dataType)) {
            $this->AddHeaders("Accept: $dataType");
        }
        $this->method = HttpMethod::GET;
        $this->ignore_errors = false;
        

        if (is_callable($success)) {
            return $this->Execute()->then($success);
        } else {
            return $this->Execute();
        }
    }
    /**
     * Makes a GET request in the same syntax as jQuery.
     * 
     * @param string $url A callback function that is executed if the request succeeds.
     * @param mixed $data Data that is sent to the server with the request.
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return HttpResponse
     */
    public static function get($url, $data = null, $success = null, $dataType = null)
    {
        $Config = array('url' => $url);
        if (!empty($data)) {
            $Config['content'] = $data;
        }

        return (new HttpRequest($Config))->_get($success, $dataType);
    }
    /**
     * Makes a POST request in a similar fashion to jQuery, but supressing $url and $data (suposed to have been already
     * set before calling this method).
     * A ContentType header with 'application/x-www-form-urlencoded' value is automatically added.
     * 
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return HttpResponse
     */
    public function _post($success = null, $dataType = null) {
        if (!empty($dataType)) {
            $this->AddHeaders("Accept: $dataType");
        }
        $this->AddHeaders('Content-Type: application/x-www-form-urlencoded');
        $this->method = HttpMethod::POST;
        $this->ignore_errors = false;

        if (is_callable($success)) {
            return $this->Execute()->then($success);
        } else {
            return $this->Execute();
        }
    }
    /**
     * Makes a POST request in the same syntax as jQuery.
     * A ContentType header with 'application/x-www-form-urlencoded' value is automatically added.
     * 
     * @param string $url A callback function that is executed if the request succeeds.
     * @param mixed $data Data that is sent to the server with the request.
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return HttpResponse
     */
    public static function post($url, $data = null, $success = null, $dataType = null) {
        $Config = array('url' => $url);
        if (!empty($data)) {
            $Config['content'] = $data;
        }

        return (new HttpRequest($Config))->_post($success, $dataType);
    }
}

/**
 * Contains the data and headers returned from a HttpRequest, providing most of the fields acessible in a
 * Javascript's fetch Response (https://developer.mozilla.org/en-US/docs/Web/API/Response), but also with some
 * functionalities of Javascript's Promises to make the logic more simple.
 *  * 
 * @property-read object $headers Every header returned by the HttpRequest.
 * @property-read bool $ok Indicates whether the response was successful (status in the range 200–299) or not.
 * @property-read bool $redirected Indicates whether or not the response is the result of a redirect (that is, its URL
 * list has more than one entry).
 * @property-read int $status The status code of the response. (This will be 200 for a success).
 * @property-read string $statusText Status message corresponding to the status code (e.g., OK for 200).
 * @property-read string $type The type of the response (e.g., basic, cors).
 * @property-read string $url The URL of the response
 * @property-read resource $body Stream to response's content
 * @property-read bool $bodyUsed Whether the body has been used in a response yet
 * 
 * @see HttpRequest
 * @see ResponseException
 */
class HttpResponse implements IResponseData {
    // Javascript Fetch API's Response imitation
    /**
     * @var object Every header returned by the HttpRequest.
     */
    protected $headers;
    /**
     * @var bool Indicates whether the response was successful (status in the range 200–299) or not.
     */
    protected $ok;
    /**
     * @var bool Indicates whether or not the response is the result of a redirect (that is, its URL list has more than one entry).
     */
    protected $redirected;
    /**
     * @var int The status code of the response. (This will be 200 for a success).
     */
    protected $status;
    /**
     * @var string Status message corresponding to the status code (e.g., OK for 200).
     */
    protected $statusText;
    /**
     * @var callable A callback resolving to a Headers object, associated with the response with Response.headers for
     * values of the HTTP Trailer header.
     * 
     * Not to be implemented yet.
     */
    // public $trailers;
    /**
     * @var string The type of the response (e.g., basic, cors).
     */
    protected $type;
    /**
     * @var string The URL of the response.
     */
    protected $url;
    /**
     * @var string Every header returned by the HttpRequest.
     * 
     * Dreprecated, not to be implemented.
     */
    // public $useFinalURL;



    // Response body
    /**
     * @var resource Stream to response's content.
     */
    protected $body;
    /**
     * @var bool Whether the body has been used in a response yet.
     * 
     */
    protected $bodyUsed;



    // Internal use properties (not to be exposed)
    /**
     * Copy of the Request that originated this Response.
     * Only set when an error occurs.
     * 
     * @var HttpRequest
     */
    protected $Request;
    /**
     * Current error reason on ._catch() sequence.
     * 
     * @var mixed
     */
    protected $CurentReason;
    /**
     * Current value on .then() sequence.
     * 
     * @var mixed
     */
    protected $CurentValue;
    /**
     * Controls wich fields are visible on __get() magical method.
     * 
     * @var string[]
     */
    protected static $VisibleFields;



    /**
     * Creates a new Result from an HttpRequest to $Stream, already computing public properties values.
     * 
     * @param resource $Stream An open Stream with HTTP Wrapper
     * @param HttpRequest $Request The request that created this object
     * @return self
     */
    public function __construct($Stream, $Request) {
        $this->body     = $Stream;
        $this->bodyUsed = false;

        if ($Stream !== false) {
            $MetaData = stream_get_meta_data($Stream);
            $Headers = $MetaData['wrapper_data'];
                    
            $this->url = $MetaData['uri'];
            // If has headers
            if (!empty($Headers) && count($Headers) > 0) {
                $this->headers = new stdClass();
                // Initially assumes everything is ok
                $this->ok = true;
    
                $LastHttpStatus = '';
                // Parsing header to a readable format
                foreach( $Headers as $Value )
                {
                    $Test = explode( ':', $Value, 2 );
                    if( isset( $Test[1] ) )
                        $this->headers->{trim($Test[0])} = trim( $Test[1] );
                    elseif( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$Value, $Output ) ) {
                        $this->headers->reponse_code = intval($Output[1]);
                        $LastHttpStatus = $Value;
                    }
                }
    
                // If response has status
                if (property_exists($this->headers, 'reponse_code')) {
                    $this->status = $this->headers->reponse_code;
                    // If response status is between 200 and 299
                    $this->ok = $this->status > 199 && $this->status < 300;
                } else {
                    $this->status = 0;
                    $this->ok = false;
                }
    
                // Detecting redirect
                $this->redirected = ($LastHttpStatus != $Headers[0]);
    
                // Dummy statusText
                $this->statusText = $this->ok ? 'OK' : '';

                // If this HttpResponse is result of a local or a remote HttpRequest
                $this->type = stream_is_local($Stream)
                    ? 'basic'
                    : 'cors'
                ;
    
                // The Request itself is passed for the first .then() call
                $this->CurentValue = $this;
            } else { // No headers = network failure or timeout
                // Avoiding use of 'opaque' type to simplify logi
                $this->type = 'error';
                $this->status = 0;
                $this->ok = false;
                $this->statusText = 'network error';
                $this->CurentReason = $MetaData['timed_out']
                    ? 'Request timed out'
                    : 'Headers not sent by remote server'
                ;
            }
        } else { // Stream being false = error
            $this->ok = false;
            $this->Request  = clone $Request;
            $this->url = $this->Request->url;

            $LastError = error_get_last();
            // If the error hapened on this file
            if ($LastError['file'] == __FILE__) {
                $Output = array();
                // eg: HTTP/1.0 405 Method Not Allowed
                preg_match("%(?:http[\/ ]\d\.\d)\s?(\d+)\s([\w\s]+)%i", $LastError['message'], $Output);
                $this->type = stream_is_local($this->Request->url)
                    ? 'basic'
                    : 'cors'
                ;
                $this->status = array_key_exists(1, $Output)
                    ? $Output[1]
                    : 0
                ;
                $this->status = array_key_exists(2, $Output)
                    ? $Output[2]
                    : 'network error'
                ;
                $this->status = array_key_exists(0, $Output)
                    ? $Output[0]
                    : 'Unknow network error'
                ;
            } else {
                $this->type = 'error';
                $this->status = 0;
                $this->statusText = 'network error';
                $this->CurentReason = 'Unknow network error';
            }
        }

        if (empty(self::$VisibleFields)) {
            self::$VisibleFields = array(
                'headers', 'ok', 'redirected', 'status', 'statusText', 'type', 'url',
                'body', 'bodyUsed'
            );
        }
    }
    /**
     * Discards the Stream used internally.
     */
    public function __destruct()
    {
        if (!empty($this->body)) {
            fclose($this->body);
        }
    }
    /**
     * Magic method __get() to make the HttpResponse properties read-only.
     * 
     * @return mixed|null Property value if it exists, null else.
     */
    public function __get($name)
    {
        if ($name == 'body') {
            $this->bodyUsed = true;
        }

        return in_array($name, self::$VisibleFields)
            ? $this->{$name}
            : null
        ;
    }



    // Fetch API-like Response methods
    /**
     * Creates a clone of a Response object.
     * 
     * @return self
     * Not to be implemented yet, depends on cloning Streams.
     */
    // public function clone() {
    //     if ($this->bodyUsed) {
    //         throw new LogicException(
    //             'Cannnot clone a Request when it\'s body was alredy read.'
    //         );
    //     }
    //     return clone $this;
    // }

    /**
     * Returns a new Response object associated with a network error.
     * 
     * Not to be implemented yet, depends on $type.
     * @return self
     */
    // public function error() {
    // }
    /**
     * Returns a new Response resulting in a redirect to the specified URL.
     * 
     * Not to be implemented yet.
     * @return self
     */
    // public function redirect($url, $status = 0) {
    // }



    // Fetch API-like Response.Body methods
    // public function arrayBuffer() {
    // }
    // public function blob() {
    // }
    // public function formData() {
    // }
    /**
     * Read's response body as decoded JSON content using native's json_decode() function.
     * 
     * @param bool $assoc When TRUE, returned objects will be converted into associative arrays.
     * @return object|array
     * @throws ResponseException If the method is called after an error occurred.
     */
    public function json($assoc = false) {
        $this->bodyUsed = true;

        if ($this->type == 'error') {
            throw new ResponseException(array(
                'message'  => 'Nothing to read, an error occurred.',
                'Response' => $this,
                'Request'  => $this->Request
            ));
        }

        return json_decode(
            stream_get_contents($this->body),
            $assoc
        );
    }
    /**
     * Read's response body as pure text.
     * 
     * @return string
     * @throws ResponseException If the method is called after an error occurred.
     */
    public function text() {
        $this->bodyUsed = true;

        if ($this->type == 'error') {
            throw new ResponseException(array(
                'message'  => 'Nothing to read, an error occurred.',
                'Response' => $this,
                'Request'  => $this->Request
            ));
        }

        return stream_get_contents($this->body);
    }



    // Fetch API-like chainable methods
    /**
     * Runs an error handler callback and return this HttpResponse, allowing chaining.
     * 
     * @param callable $rejected
     * @return self
     */
    public function _catch($rejected) {
        if ($this->type == 'error' && is_callable($rejected)) {
            $this->CurentReason = call_user_func($rejected, $this->CurentReason);
        }

        return $this;
    }
    /**
     * Runs a handler callback, no matter the status of the response.
     * 
     * @param callable $onFinally
     */
    public function _finally($settled) {
        if (is_callable($settled)) {
            call_user_func($settled);
        }

        // Closing body after finally
        if (!empty($this->body)) {
            fclose($this->body);
            $this->body = null;
            $this->bodyUsed = true;
        }
    }
    /**
     * Runs a success handler callback and, optionally error handler callback, choosing wich to execute based on
     * response status.
     * Returns this HttpResponse, allowing chaining.
     * 
     * @param callable $fulfilled
     * @param callable $rejected
     * @return self
     */
    public function then($fulfilled, $rejected = null) {
        if ($this->type != 'error' && is_callable($fulfilled)) {
            $this->CurentValue = call_user_func($fulfilled, $this->CurentValue);
        } elseif (is_callable($rejected)) {
            $this->CurentReason = call_user_func($rejected, $this->CurentReason);
        }

        return $this;
    }



    // jqXHR like chainable methods
    /**
     * Runs one or many handler callbacks, no matter the status of the response.
     * Alias to HttpResponse::_finally($alwaysCallbacks) when used with a single callback as parameter.
     * 
     * @param callable|callable[] $alwaysCallbacks
     * @return self
     */
    public function always($alwaysCallbacks) {
        if (is_array($alwaysCallbacks)) {
            foreach ($alwaysCallbacks as $Callback) {
                $this->_finally($Callback);
            }
        } else {
            $this->_finally($alwaysCallbacks);
        }

        return $this;
    }
    /**
     * Runs one or many success handler callbacks.
     * Alias to HttpResponse::then($doneCallbacks) when used with a single callback as parameter.
     * 
     * @param callable|callable[] $doneCallbacks
     * @return self
     */
    public function done($doneCallbacks) {
        if (is_array($doneCallbacks)) {
            foreach ($doneCallbacks as $Callback) {
                $this->then($Callback);
            }
        } else {
            $this->then($doneCallbacks);
        }

        return $this;
    }
    /**
     * Runs one or many error handler callbacks.
     * Alias to HttpResponse::_catch($failCallbacks) when used with a single callback as parameter.
     * 
     * @param callable|callable[] $failCallbacks
     * @return self
     */
    public function fail($failCallbacks) {
        if (is_array($failCallbacks)) {
            foreach ($failCallbacks as $Callback) {
                $this->_catch($Callback);
            }
        } else {
            $this->_catch($failCallbacks);
        }

        return $this;
    }
}

/**
 * A ResponseException is thrown when an HttpRequest fails due to a network error
 * (like a ssl reset, for example).
 * 
 * @param string $message The Exception message to throw.
 * @param int $code The Exception code.
 * @param Throwable $previous The previous exception used for the exception chaining.
 * 
 * @property HttpRequest $Request Request in witch the response was thrown
 * @property HttpResponse $Response Response 
 * 
 * @see HttpRequest
 */
class ResponseException extends Exception {
    /**
     * HttpRequest send to the server.
     * 
     * @var HttpRequest
     */
    protected $Request;
    /**
     * Response returned by the server.
     * 
     * @var HttpResponse
     */
    protected $Response;



    /**
     * Creates a new RequestException to indicate that a Request failed.
     * 
     * @param array|string $Data Array with all propeties of the class or a simple message string
     * @param int|string $code (optional) Error code
     * @param \Throwable $previous (optional) Previous exception on the call stack
     */
    public function __construct($Data = null, $code = 0, $previous = null) {
        if (is_array($Data)) {
            // Setting values
            foreach ($Data as $Name => $Value) {
                if (property_exists($this, $Name)) {
                    $this->{$Name} = $Value;
                }
            }
            if (empty($this->message)) {
                $this->message = '';
            }
            if (empty($this->code)) {
                $this->code = 0;
            }
            if (empty($this->previous)) {
                $this->previous = null;
            }

            $IntCode = is_int($this->code);

            parent::__construct(
                $this->message,
                $IntCode ? $this->code : 0,
                $this->previous
            );

            if (!$IntCode) {
                $this->code = $code;
            }
        } else {
            $IntCode = is_int($code);

            parent::__construct(
                $Data,
                $IntCode ? $code : 0,
                $previous
            );

            if (!$IntCode) {
                $this->code = $code;
            }
        }
    }
    /**
     * Returns a field that can be only read.
     * 
     * @return string $name Name of the field to return.
     * @return mixed
     */
    public function __get($name) {
        return property_exists($this, $name)
            ? $this->{$name}
            : null
        ;
    }

    /**
     * Represents the Exception as a JSON string, adding all registered sequence of previous exceptions as a stack.
     * 
     * @return string
     */
    public function __toString() {
        $Data = [
            'status'  => $this->code,
            'message' => $this->message,
            'file'    => $this->getFile(),
            'line'    => $this->getLine(),
            'stack'   => []
        ];

        /**
         * @var Throwable
         */
        $Previous = $this->getPrevious();
        while($Previous != null) {
            $Data['stack'][] = [
                'status'  => $Previous->getCode(),
                'message' => $Previous->getMessage(),
                'file'    => $this->getFile(),
                'line'    => $this->getLine()
            ];

            $Previous = $Previous->getPrevious();
        }

        return json_encode($Data);
    }
}



/**
 * Encapsulates the File Upload logic, providing more a more reasonable way to deal with the files.
 * 
 * @property-read string $Name The original name of the file on the client machine.
 * @property-read string $Type The mime type of the file, checked on the server side instead of believing on what the
 * browser says.
 * @property-read int $Size The size, in bytes, of the uploaded file.
 * @property-read string $Path The temporary path of the file in which the uploaded file was stored on the server or
 * the updated path if the file was moved.
 * @property-read string $Moved If the file was moved by Move() or Save() method.
 * 
 * @see UploadException
 */
class HttpUploadFile {
    /**
     * Encapsulates a single file from $_FILES, returning a HttpUploadFile instance.
     * If multiple files were sent with $HtmlName, only the first will be taken.
     * 
     * @param string $HtmlName Name attribute of the Form Element used to submit the file
     * @return HttpUploadFile
     */
    public static function GetSingle($HtmlName) {
        // Checking if there is a file
        if (!array_key_exists($HtmlName, $_FILES)) {
            throw new BadFunctionCallException(
                "No file was uploaded with $HtmlName name attribute."
            );
        }

        // Gets only the first file if multiple were uploaded
        if (is_array($_FILES[$HtmlName]['name'])) {
            // Checking for upload errors
            if ($_FILES[$HtmlName]['error'][0] != UPLOAD_ERR_OK) {
                throw new UploadException($_FILES[$HtmlName]['error']);
            }

            return new HttpUploadFile(
                $_FILES[$HtmlName]['name'][0],
                $_FILES[$HtmlName]['size'][0],
                $_FILES[$HtmlName]['tmp_name'][0]
            );
        } else {
            // Checking for upload errors
            if ($_FILES[$HtmlName]['error'] != UPLOAD_ERR_OK) {
                throw new UploadException($_FILES[$HtmlName]['error']);
            }

            return new HttpUploadFile(
                $_FILES[$HtmlName]['name'],
                $_FILES[$HtmlName]['size'],
                $_FILES[$HtmlName]['tmp_name']
            );
        }
    }
    /**
     * Encapsulates multiple files from $_FILES, returning an array with a HttpUploadFile instance for each of them.
     * Only reads a 2D $_FILES array, more complex structures won't work.
     * 
     * @param string $HtmlName Name attribute of the Form Element used to submit the file
     * @return HttpUploadFile[]
     */
    public static function GetAll($HtmlName) {
        // Checking if there is a file
        if (!array_key_exists($HtmlName, $_FILES)) {
            throw new BadFunctionCallException(
                "No file was uploaded with $HtmlName name attribute."
            );
        }

        /**
         * @var HttpUploadFile[]
         */
        $Result = array();

        if (is_array($_FILES[$HtmlName]['name'])) {
            // Autistic loop made by PHP's *wonderful* multiple upload scheme
            foreach ($_FILES[$HtmlName]['error'] as $Index => $Error) {
                // Checking for upload errors on each file
                if ($Error != UPLOAD_ERR_OK) {
                    throw new UploadException($Error);
                }

                $Result[] = new HttpUploadFile(
                    $_FILES[$HtmlName]['name'][$Index],
                    $_FILES[$HtmlName]['size'][$Index],
                    $_FILES[$HtmlName]['tmp_name'][$Index]
                );
            }
        } else { // If there's only one file, will be added to array
            // Checking for upload errors
            if ($_FILES[$HtmlName]['error'] != UPLOAD_ERR_OK) {
                throw new UploadException($_FILES[$HtmlName]['error']);
            }

            $Result[] = new HttpUploadFile(
                $_FILES[$HtmlName]['name'],
                $_FILES[$HtmlName]['size'],
                $_FILES[$HtmlName]['tmp_name']
            );
        }

        return $Result;
    }



    /**
     * The original name of the file on the client machine.
     * 
     * @var string
     */
    protected $Name;
    /**
     * The mime type of the file, checked on the server side instead of believing on what the browser says.
     * 
     * @var string
     */
    protected $Type;
    /**
     * The size, in bytes, of the uploaded file.
     * 
     * @var int
     */
    protected $Size;
    /**
     * The temporary path of the file in which the uploaded file was stored on the server.
     * If the file was successfully moved by .Move() or .Save(), will contain the updated path.
     * 
     * @var string
     */
    protected $Path;
    /**
     * If the file was already moved using a call to either .Move() or .Save().
     * 
     * @var bool
     */
    protected $Moved;



    /**
     * Creates a new HttpUploadFile instance, checking internally it's mime type.
     * 
     * @internal
     * @param string $name Original name of the file, aka $_FILES[{whathever}]['name']
     * @param int $size Size of the file, aka $_FILES[{whathever}]['size']
     * @param string $tmp_name Temporary path of the file on server, aka $_FILES[{whathever}]['tmp_name']
     */
    public function __construct($name, $size, $tmp_name) {
        $this->Name = $name;
        $this->Size = $size;
        $this->Path = $tmp_name;
        $this->Moved = false;

        $Type = @mime_content_type($tmp_name);
        $this->Type = is_string($Type) ? $Type : '';
    }
    /**
     * Magic method __get() to make the HttpResponse properties read-only.
     * 
     * @param string $name
     * @return mixed|null Property value if it exists, null else.
     */
    public function __get($name)
    {
        return property_exists($this, $name)
            ? $this->{$name}
            : null
        ;
    }
    /**
     * Allows reading the file content typecasting this object to a string.
     * If the conent can't be read as string, an empty string is returned.
     * 
     * @return string
     */
    public function __toString() {
        $Content = file_get_contents($this->Path);

        return is_string($Content) ? $Content : '';
    }



    /**
     * Moves an uploaded file to a new location. If the file is already moved, returns false.
     * 
     * @param string $destination The destination of the moved file.
     * @return bool
     */
    public function Move($destination) {
        if (!$this->Moved) {
            $Result = move_uploaded_file($this->Path, $destination);

            if ($Result) {
                $this->Path = $destination;
            }
        } else {
            $Result = false;
        }

        return $Result;
    }
    /**
     * Reads the contents of the file to the output.
     * 
     * @return int|false the number of bytes read from the file. If an error occurs, false is returned.
     */
    public function Output() {
        return readfile($this->Path);
    }
    /**
     * Saves the contents of an uploaded file. If the file is already moved, returns false.
     * Alias to .Move(string)
     * 
     * @param string $filename The name of the saved file.
     * @return bool
     */
    public function SaveAs($filename) {
        return $this->Move($filename);
    }
    /**
     * Returns the Base64 string representation of the file content.
     * If the content can't be read as string, an empty string is returned.
     * 
     * @return string
     */
    public function ToBase64() {
        $Content = file_get_contents($this->Path);
        
        return is_string($Content) ? base64_encode($Content) : '';
    }
    /**
     * Returns a binary array with the file content.
     * If the content can't be read as string, an empty array is returned.
     * 
     * @return int[]
     */
    public function ToBinaryArray() {
        $Content = file_get_contents($this->Path);
        
        return is_string($Content) ? unpack('N*', $Content) : array();
    }
    /**
     * Returns the string representation of the file content.
     * If the content can't be read as string, an empty string is returned.
     * 
     * @return string
     */
    public function ToString() {
        $Content = file_get_contents($this->Path);
        
        return is_string($Content) ? $Content : '';
    }
}

/**
 * An UploadException is thrown when an error is found on a HttpUploadFile, making easier to spot upload errors.
 * 
 * This is based on danbrown's comment on PHP's manual.
 * @see HttpUploadFile
 * @see https://www.php.net/manual/en/features.file-upload.errors.php
 */
class UploadException extends Exception {
    public function __construct($code, $previous = null) {
        $message = $this->codeToMessage($code);
        parent::__construct($message, $code, $previous);
    }

    protected function codeToMessage($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }

        return $message;
    }
}



/**
 * Indicates that the object is meant to be sent in a HTTP request.
 * 
 * Marker interface, no functionality provided.
 */
interface IRequestData {
}
/**
 * Indicates that the object is result of a HTTP request.
 * 
 * Marker interface, no functionality provided.
 */
interface IResponseData {
}