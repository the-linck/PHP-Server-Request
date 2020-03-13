<?php
namespace Http;



/**
 * Dummy container to represent Http Methods in a standard way.
 * All methods in https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods are listed, in the same order.
 */
abstract class Method implements IEnum {
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
class Request {
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
     * Creates a new Response instance, that may be reused several times.
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
     * Internal method to open a stream for this Request, creating a Response object to deal with the
     * [meta]data and allow Javascript's Fetch-like usage.
     * 
     * @return Response
     */
    protected function Execute() {
        $Config = array(
            'method' => empty($this->method) ? Method::GET : strtoupper($this->method)
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
        return new Response(@fopen($this->url, 'rb', false, $Context), $this);
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
     * @return Response
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
     * @return Response
     */
    public static function fetch($resource, $init = array())
    {
        return (new Request(array('url' => $resource)))->_fetch($init);
    }



    // jQuery's ajax imitation
    /**
     * Makes a GET request in a similar fashion to jQuery, but supressing $url and $data (suposed to have been already
     * set before calling this method).
     * 
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return Response
     */
    public function _get($success = null, $dataType = null)
    {
        if (!empty($dataType)) {
            $this->AddHeaders("Accept: $dataType");
        }
        $this->method = Method::GET;
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
     * @return Response
     */
    public static function get($url, $data = null, $success = null, $dataType = null)
    {
        $Config = array('url' => $url);
        if (!empty($data)) {
            $Config['content'] = $data;
        }

        return (new Request($Config))->_get($success, $dataType);
    }
    /**
     * Makes a POST request in a similar fashion to jQuery, but supressing $url and $data (suposed to have been already
     * set before calling this method).
     * A Content-Type header with 'application/x-www-form-urlencoded' value is added if no Content-Type was set yet.
     * 
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return Response
     * @see ContentType::URL_ENCODED
     */
    public function _post($success = null, $dataType = null) {
        if (!empty($dataType)) {
            $this->AddHeaders("Accept: $dataType");
        }
        if (!array_key_exists('Content-Type', $this->header)) {
            $this->AddHeaders('Content-Type: ' . ContentType::URL_ENCODED);
        }
        $this->method = Method::POST;
        $this->ignore_errors = false;

        if (is_callable($success)) {
            return $this->Execute()->then($success);
        } else {
            return $this->Execute();
        }
    }
    /**
     * Makes a POST request in the same syntax as jQuery.
     * A Content-Type header with 'application/x-www-form-urlencoded' value is added if no Content-Type was set yet.
     * 
     * @param string $url A callback function that is executed if the request succeeds.
     * @param mixed $data Data that is sent to the server with the request.
     * @param callable $success A callback function that is executed if the request succeeds.
     * @param string $dataType The type of data expected from the server. 
     * @return Response
     */
    public static function post($url, $data = null, $success = null, $dataType = null) {
        $Config = array('url' => $url);
        if (!empty($data)) {
            $Config['content'] = $data;
        }

        return (new Request($Config))->_post($success, $dataType);
    }
}

/**
 * Contains the data and headers returned from a Request, providing most of the fields acessible in a
 * Javascript's fetch Response (https://developer.mozilla.org/en-US/docs/Web/API/Response), but also with some
 * functionalities of Javascript's Promises to make the logic more simple.
 *  * 
 * @property-read object $headers Every header returned by the Request.
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
 * @see Request
 * @see ResponseException
 */
class Response implements IResponseData {
    // Javascript Fetch API's Response imitation
    /**
     * @var object Every header returned by the Request.
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
     * @var string Every header returned by the Request.
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
     * 
     * @var Request
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
     * Creates a new Result from an Request to $Stream, already computing public properties values.
     * 
     * @param resource $Stream An open Stream with HTTP Wrapper
     * @param Request $Request The request that created this object
     * @return self
     */
    public function __construct($Stream, $Request) {
        $this->body     = $Stream;
        $this->bodyUsed = false;
        
        $HasRequest = is_object($Request);
        if ($HasRequest) {
            $this->Request = clone $Request;
        }
        if ($Stream !== false) {
            $MetaData = stream_get_meta_data($Stream);
            $Headers = $MetaData['wrapper_data'];
                    
            $this->url = $MetaData['uri'];
            // If has headers
            if (!empty($Headers) && count($Headers) > 0) {
                $this->headers = new \stdClass();
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

                // If this Response is result of a local or a remote Request
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

            if ($HasRequest) {
                $this->url = $this->Request->url;
            }

            $LastError = error_get_last();
            // If the error hapened on this file
            if ($LastError['file'] == __FILE__ && $HasRequest) {
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
                $this->statusText = '';
                $this->CurentReason = 'Unknow network error';
                $this->body = null;
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
    public function __destruct() {
        if (!empty($this->body)) {
            fclose($this->body);
        }
    }
    /**
     * Magic method __get() to make the Response properties read-only.
     * 
     * @return mixed|null Property value if it exists, null else.
     */
    public function __get($name) {
        if ($name == 'body') {
            $this->bodyUsed = true;
        }

        return in_array($name, self::$VisibleFields)
            ? $this->{$name}
            : null
        ;
    }
    /**
     * Returns a one dimension version of the array, creating complex keys for nested arrays.
     * Duplicate keys are not ovewrited.
     * 
     * @param array $Source
     * @param string $Prefix
     * @return array
     */
    public function FlattenArray($Source, $Prefix = '') {
        $Result = array();

        foreach ($Source as $OriginalKey => $Value) {
            if ($Prefix == '') {
                $Key = $OriginalKey;
            } elseif (is_int($OriginalKey)) {
                // Keeping same format used by PHP's to parse Querystring
                $Key = "{$Prefix}[]";
            }else{
                $Key = "$Prefix.$OriginalKey";
            }
            
            if (is_array($Value)) {
                $Result += $this->FlattenArray($Value, $Key);
            } else {
                $Result[$Key] = $Value;
            }
        }

        return $Result;
    }
    /**
     * Checks if given string is binary.
     * 
     * @param string $String
     * @return bool
     */
    public function IsBinary($String) {
        return !ctype_print($String) || strpos($String, "\0");
    }



    // Fetch API-like Response methods
    /**
     * Creates a clone of this Response object, allowing a new use of the response body (on the new object).
     * This method is recommended over native PHP cloning, due to stream cloning logic.
     * 
     * @return self
     * @throws LogicException If the response body has already been read
     */
    public function _clone() {
        if ($this->bodyUsed) {
            throw new \LogicException(
                'Cannnot clone a Request when it\'s body was alredy read.'
            );
        }
        $Result = clone $this;
        
        $Copy = fopen('php://temp', 'wb+');
        stream_copy_to_stream($this->body, $Copy);
        rewind($Copy);

        $this->body = fopen('php://temp', 'rb+');
        stream_copy_to_stream($Copy, $this->body);
        rewind($this->body);
        
        rewind($Copy);
        $Result->body = fopen('php://temp', 'rb+');
        stream_copy_to_stream($Copy, $Result->body);
        rewind($Result->body);

        fclose($Copy);

        return $Result;
    }
    /**
     * Returns a new Response object associated with a network error.
     * 
     * @return self
     */
    public static function error() {
        return new Response(false, null);
    }
    /**
     * Returns a new Response resulting in a redirect to the specified URL.
     * 
     * @return self
     */
    public static function redirect($url, $status = 0) {
        if ($status == 0) {
            $status = 302;
        } else {
            switch ($status) {
                case 301:
                case 302:
                case 303:
                case 307:
                case 308:
                    break;
                default:
                    throw new \UnexpectedValueException("Invalid redirect status");
            }
        }

        $Result = new Response(false, null);
        $Result->status = $status;
        $Result->url = $url;
        $Result->type = stream_is_local($url)
            ? 'basic'
            : 'cors'
        ;
        $Result->redirected = true;
        $Result->CurentReason = null;

        return $Result;
    }



    // Fetch API-like Response.Body methods
    /**
     * Reads response body as a byte array.
     * 
     * @param string $Format (optional)
     * @return array
     * @throws ResponseException If the method is called after an error occurred.
     */
    public function arrayBuffer($Format = 'N*') {
        $this->bodyUsed = true;

        if ($this->type == 'error') {
            throw new ResponseException(array(
                'message'  => 'Nothing to read, an error occurred.',
                'Response' => $this,
                'Request'  => $this->Request
            ));
        }

        return unpack($Format, stream_get_contents($this->body));
    }
    /**
     * Reads response body as a binary data string.
     * A non-binary body will be assumed to be in Hexadecimal and translated from that.
     * 
     * @return string Binary string
     */
    public function blob() {
        $this->bodyUsed = true;

        if ($this->type == 'error') {
            throw new ResponseException(array(
                'message'  => 'Nothing to read, an error occurred.',
                'Response' => $this,
                'Request'  => $this->Request
            ));
        }

        $Content = stream_get_contents($this->body);
        
        if ($this->IsBinary($Content)) {
            return $Content;
        } else {
            return pack('H*', $Content);
        }
    }
    /**
     * Reads response body as an associative array, mimicking Javascript's FormData.
     * If the body content is multidimensional (like a JSON object with neted objects), it will be flattened.
     * 
     * @return array
     */
    public function formData() {
        $Content = stream_get_contents($this->body);
        // trying to decode as JSON
        $JSON = json_decode($Content, true);
        if (json_last_error() == JSON_ERROR_NONE) { // JSON Content
            $Result = $this->FlattenArray($JSON);
        } else { // Querystring or application/x-www-form-urlencoded
            $Result = array();
            parse_str($Content, $Result);
        }
        
        return $Result;
    }
    /**
     * Reads response body as decoded JSON content using native's json_decode() function.
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
     * Reads response body as pure text.
     * A binary body will be converted to Hexadecimal representation.
     * 
     * @return string Pure text string
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

        $Content = stream_get_contents($this->body);
        
        if ($this->IsBinary($Content)) {
            return implode('', unpack('H*', $Content));
        } else {
            return $Content;
        }
    }



    // Fetch API-like chainable methods
    /**
     * Runs an error handler callback and return this Response, allowing chaining.
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
     * Returns this Response, allowing chaining.
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
     * Alias to Response::_finally($alwaysCallbacks) when used with a single callback as parameter.
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
     * Alias to Response::then($doneCallbacks) when used with a single callback as parameter.
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
     * Alias to Response::_catch($failCallbacks) when used with a single callback as parameter.
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
 * A ResponseException is thrown when an Request fails due to a network error
 * (like a ssl reset, for example).
 * 
 * @param string $message The Exception message to throw.
 * @param int $code The Exception code.
 * @param Throwable $previous The previous exception used for the exception chaining.
 * 
 * @property Request $Request Request in witch the response was thrown
 * @property Response $Response Response 
 * 
 * @see Request
 */
class ResponseException extends \Exception {
    /**
     * Request send to the server.
     * 
     * @var Request
     */
    protected $Request;
    /**
     * Response returned by the server.
     * 
     * @var Response
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
 * Dummy container to represent common Content-Type headers in a standard way.
 * 
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods/POST
 */
abstract class ContentType implements IEnum {
    /**
     * Binary file.
     * 
     * @var string
     */
    const BINARY = 'application/octet-stream';
    /**
     * Request: Each value is sent as a block of data ("body part"), with a user agent-defined delimiter ("boundary")
     * separating each part. The keys are given in the Content-Disposition header of each part.
     * 
     * @var string
     */
    const FORM_DATA = 'multipart/form-data';
    /**
     * Request: JSON encoded data, be it an object, an array or a JSON string. Response: JSON encoded string.
     * It should be encoded in UTF-8 and always used double quotes for strings.
     * 
     * @var string
     */
    const JSON = 'application/json';
    /**
     * Lightweight Linked Data format basead on JSON that provides a way to help JSON data interoperate at Web-scale.
     * Ideal data format for programming environments, REST Web services, and unstructured databases.
     * 
     * @var string
     */
    const JSON_LD = 'application/ld+json';
    /**
     * Request: Spaces are converted to "+" symbols, but no special characters are encoded. Response: .txt files.
     * 
     * @var string
     */
    const TEXT = 'text/plain';
    /**
     * Unknown file type.
     * 
     * @var string
     */
    const UNKNOW = 'application/octet-stream';
    /**
     * Request: Keys and values are encoded in key-value tuples separated by '&', with a '=' between the key
     * and the value.
     * Non-alphanumeric characters in both keys and values are percent encoded.
     * 
     * @var string
     */
    const URL_ENCODED = 'application/x-www-form-urlencoded';
    /**
     * XML not readable from casual users; .xml files.
     * 
     * @var string
     */
    const XML = 'application/xml';
    /**
     * XTML readable from casual users; .xml files.
     * 
     * @var string
     */
    const XML_PUBLIC = 'text/xml';
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
/**
 * Indicates that the class is an enumeration of values for HTTP Requests/Responses.
 * 
 * Marker interface, no functionality provided.
 */
interface IEnum {
}