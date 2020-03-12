# PHP-Server-Request

This library mimics Javascript's Fetch API in PHP, allowing you to easily make server-side requests the same way you would do in JS - with minor syntax differences.

It also provides better management for uploaded files, some useful interfaces and exceptions, all of them really simple to use.

Under the hood only native resources of the language are used to configure everything and make the requests, so there's nothing to install on the server for this lib to work - just import/require and use it.

>***Notice:** you may need to enable PHP's openssl module if  to make HTTPS requests.*

---



## HttpMethod Enumeration

To make the code more readable and avoiding having to deal with string case, an "enum" with the 9 [HTTP methods](https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods) is provided.

It is used internally and very recomended on any code for HTTP requests.

---



## Request

The boring logic to make server-side requests through HTTP Stream wrapper is encapsulated in the *HttpRequest* class. An object of this class may be reused several times, just changing the fields values as needed.



### Fields

A *HttpRequest* object has the same fields that may be passed to the Http Stream wrapper and the url to be called. The fields are listed bellow:

* *string* **method**  
Any HTTP method supported by the remote server, defaults to GET
* *array* **header**  
Associative array of additional headers to be sent during request, such as User-agent, Host, and Authentication
* *string* **user_agent**  
Value to send with User-Agent: header, only be used if user-agent is not specified in the header
* *string* **content**  
Additional data to be sent after the headers typically used with POST or PUT
* *string* **proxy**  
URI specifying address of proxy server
* *bool* **request_fulluri**  
When set to TRUE, the entire URI will be used when constructing the request
* *integer* **follow_location**  
The max number of redirects to follow, 1 or less means that no redirects are followed
* *float* **protocol_version**  
HTTP protocol version
* *float* **timeout**  
Read timeout in seconds, specified by a float
* *bool* **ignore_errors**  
Fetch the content even on failure status codes
* *string* **url**  
URL to call on the request

All those fields are accessible to read and write. Except for url, they are passed to the Stream wrapper only when assigned (not null).



### Methods

Beign made to mimic fetch, obviously *HttpRequest* would have a method with the same name to be called. Two of them actually: one for the object and one for the class (static).
These public methods are provided:

* **__construct**([array *$Options*])  
Constructor that may receive an associative array to set all the object fields rightaway
* **AddHeaders**(string|array *$Headers*) : *self*  
Adds the given *$Headers* to the request, ovewriting previous headers with the same name
* **RemoveHeaders**(string|array *$Headers*) : *self*  
Removes the given *$Headers* from Request
* **_fetch**(array *$init*) : *HttpResponse*  
Imitation of Javasctript's fetch method, without url parameter (already on the class)  
Currently suports this fetch options on init: method, headers, body, redirect
* static **fetch**(string *$resource*, array *$init*) : *HttpResponse*  
Static version of *_fetch*, with PHP-equivalent syntax of Javascript's fetch()
* **_get**(callable *$success*, string $dataType) : *HttpResponse*  
Makes a GET request in a similar fashion to jQuery, but supressing $url and $data (already on the class)
* static **get**(string *$url*, mixed *$data*, callable *$success*, string $dataType) : *HttpResponse*  
Makes a GET request in the same syntax as jQuery
* **_post**(callable *$success*, string $dataType) : *HttpResponse*  
Makes a POST request in a similar fashion to jQuery, but supressing $url and $data(already on the class)  
Sets ContentType header to 'application/x-www-form-urlencoded'
* static **post**(string *$url*, mixed *$data*, callable *$success*, string $dataType) : *HttpResponse*  
Makes a POST request in the same syntax as jQuery.

---



## Response

The *HttpResponse* object contains the data and headers returned from a HttpRequest, providing most of the fields acessible in a  Javascript's fetch [Response](https://developer.mozilla.org/en-US/docs/Web/API/Response), but also with functionalities of Javascript's Promises to make the logic more simple.



### Fields

All fields on *HttpResponse* are read only (protected with custom "magical" getter), making the object immutable (in theory).

These fields are provided:

* *object* **headers**  
Every header returned by the HttpRequest
* *bool* **ok**  
Indicates whether the response was successful (status in the range 200–299) or not
* *bool* **redirected**  
Indicates whether or not the response is the result of a redirect
* *int* **status**  
The status code of the response. (This will be 200 for a success)
* *string* **statusText**  
Status message corresponding to the status code (e.g., OK for 200)
* *string* **type**  
The type of the response (e.g., basic, cors)
* *string* **url**  
The URL of the response
* *resource* **body**  
Stream to response's content
* *bool* **bodyUsed**  
Whether the body has been used in a response yet



### Methods

These methods from Javascript's [Promise](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise) object are implemented:

* **catch**(callable *$rejected*) : *self*  
Runs an error handler callback and return this HttpResponse for chaining
* **finally**(callable *$settled*) : void  
Runs a handler callback, no matter the status of the response, does not allow chaining
* **then**(callable *$fulfilled*, [callable *$rejected*]) : *self*  
Runs a succes handler callback and, optionally error handler callback, returning this HttpResponse for chaining

>***Notice:** due to PHP's synchronous nature, you should call Response->catch() before Response->then() to deal with errors.* 

Also, these methods from Javascript's [Response](https://developer.mozilla.org/en-US/docs/Web/API/Response) object are implemented too:

* **json**([bool *$assoc*]) : *object|array*  
Read's response body as decoded JSON content
* **finally**() : *string*  
Read's response body as pure text


And, as in HttpRequest, there are also some methods to mimic jQuery's functions:

* **always**(callable|callable[] *$alwaysCallbacks*) : void  
Runs one or many handler callbacks, no matter the status of the response
* **done**(callable|callable[] *$doneCallbacks*) : *self*  
Runs one or many success handler callbacks
* **catch**(callable|callable[] *$failCallbacks*) : *self*  
Runs one or many error handler callbacks

---



## FileUpload

Encapsulates the File Upload logic, providing more a more reasonable way to deal with the files.

### Fields

All provided fields are read-only.

* *string* **Name**  
The original name of the file on the client machine
* *string* **Type**  
The mime type of the file, checked on the server side instead of believing on what the browser says
* *int* **Size**  
The size, in bytes, of the uploaded file
* *string* **Path**  
The temporary path of the file in which the uploaded file was stored on the server or the updated path if the file was moved
* *string* **Moved**  
If the file was moved by Move() or Save() method



### Methods

These public methods are provided:

* static **GetSingle**(string *$HtmlName*) : HttpUploadFile  
Encapsulates a single file from $_FILES, returning a HttpUploadFile instance
* static **GetAll**(string *$HtmlName*) : *HttpUploadFile[]*  
Encapsulates multiple files from $_FILES, returning an array with a HttpUploadFile instance for each of them
* **__construct**(string *$name*, int *$size*, string *$tmp_name*)
Creates a new HttpUploadFile instance, checking internally it's mime type
* **__toString**() : *string*  
Allows to read the file content typecasting the *HttpUploadFile* itself to a string
* **Move**(string *$destination*) : *bool*  
Moves an uploaded file to a new location
* **Output**() : *int|false*  
Reads the contents of the file to the output
* **SaveAs**(string *$filename*) : *bool*  
Saves the contents of an uploaded file, alias to *Move*()
* **ToBase64**() : *string*  
Returns the Base64 string representation of the file content
* **ToBinaryArray**() : *int[]*  
Makes a POST request in the same syntax as jQuery.
* **ToString**() : *string*  
Returns the string representation of the file content

---



## Marker Interfaces

Three marker interfaces are provided in this lib:

* **IEnum**  
Indicates that the class is a fixed enumeration of values. Used on *HttpMethod*
* **IRequestData**  
Indicates that the object is meant to be sent in a HTTP request
* **IResponseData**  
Indicates that the object is result of a HTTP request. Used on *HttpResponse*

---



## Exceptions

The following exceptions are shipped with the library, both thrown only at veryspecific cases. 

* **ResponseException**  
A *ResponseException* is thrown when an HttpRequest fails due to a network error (like a ssl reset, for example)  
When made by this library, the Exception will contain the Request where it happened and the Response returned by the server.
* **UploadException**  
An UploadException is thrown when an error is found on a HttpUploadFile, making easier to spot upload errors



The *UploadException* is based on [danbrown's comment on PHP's manual](https://www.php.net/manual/en/features.file-upload.errors.php).