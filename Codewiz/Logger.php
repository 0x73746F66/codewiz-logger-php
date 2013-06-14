<?php
/**
 * This file contains Codewiz_Logger class
 *
 * @package    Codewiz_Logger
 * @author     Chris Langton <clangton@rockinfo.com.au>
 */
class Codewiz_Logger {
    private static $_defaults = array();
    private static $_errorType = array();
    private static $_config;
    private static $_db;
    private $_settings = array(); // Extends $_default
    private $_type = "debug";     // Used by _log()
    private $_errArr = array();   // Used by the API

    /**
     * Call constructor with $register = true only from Bootstrap
     *
     * @access  public
     * @param   array $settings an array of setting to extend from defaults
     * @param   bool $register true only from Bootstrap and only used once unless first calling restoreEnvironment()
     * @return  Codewiz_Logger instance
     */
    public function __construct( array $settings = array() , $register = false ) {
        $this->configure( $settings );
        if ( $register === true ) $this->registerHandlers();
        return $this;
    }
    
    /**
     * Call configure from constructor for instance specific settings overrides
     *
     * @access  private
     * @param   array $settings an array of setting to extend from defaults
     * @return  Codewiz_Logger instance
     */
    private function configure( array $settings = array() )
    {
        if ( count( static::$_errorType ) == 0 ) {
            // http://ca2.php.net/manual/en/errorfunc.constants.php
            static::$_errorType = array(
               E_ERROR              => 'ERROR',
               E_WARNING            => 'WARNING',
               E_PARSE              => 'PARSING ERROR',
               E_NOTICE             => 'NOTICE',
               E_CORE_ERROR         => 'CORE ERROR',
               E_CORE_WARNING       => 'CORE WARNING',
               E_COMPILE_ERROR      => 'COMPILE ERROR',
               E_COMPILE_WARNING    => 'COMPILE WARNING',
               E_USER_ERROR         => 'USER ERROR',
               E_USER_WARNING       => 'USER WARNING',
               E_USER_NOTICE        => 'USER NOTICE',
               E_STRICT             => 'STRICT NOTICE',
               E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR'
            );
        }
        if ( count( static::$_defaults ) == 0 ) {
            // check for frontend or backend base dir path const
            $basePath = defined( "APPLICATION_PATH" ) ? APPLICATION_PATH : dirname(__FILE__) ;
            // default settings
            static::$_defaults = array(
                "destination"       => array(
                                    "default"   => array(
                                                    "db",
                                                ),
                                    "debug"     => array(
                                                    "db",
                                                ),
                                    "error"     => array(
                                                    "file",
                                                    "db",
                                                ),
                                    "exception" => array(
                                                    "file",
                                                    "db",
                                                    "email",
                                                ),
                                    "fatal"     => array(
                                                    "file",
                                                    "db",
                                                    "email",
                                                ),
                                ),
                "enableHandler"      => array(
                                    "error"     => false,
                                    "exception" => false,
                                    "fatal"     => false,
                                ),
                "global"        => array(
                                    "display_errors"    => ini_get('display_errors'),
                                    "error_reporting"   => error_reporting(),
                                ),
                "restore"       => array(
                                    "errorHandler"             => false,
                                    "exceptionHandler"         => false,
                                    "display_errors"    => false,
                                    "error_reporting"   => false,
                                ),
                "adminEmail"    => "example@gmail.com",
                "emails"         => array(
                                    "content"       => "multi", // options: plaintext, html, multi
                                    "details"       => true,
                                ),
                "dateFormat"    => "Y-m-d h:i:s",
                "files"         => array(
                                    "default"       => array(
                                                        "path"    => $basePath  . '/../logs/',
                                                        "name"    => "error.log",
                                                        "mode"    => "a",
                                                        "details" => false,
                                                    ),
                                    "debug"         => array(
                                                        "path"    => $basePath  . '/../logs/',
                                                        "name"    => "error.log",
                                                        "mode"    => "a",
                                                        "details" => false,
                                                    ),
                                    "error"         => array(
                                                        "path"    => $basePath  . '/../logs/',
                                                        "name"    => "error.log",
                                                        "mode"    => "a",
                                                        "details" => false,
                                                    ),
                                    "exception"     => array(
                                                        "path"    => $basePath  . '/../logs/',
                                                        "name"    => "exception.log",
                                                        "mode"    => "a",
                                                        "details" => true,
                                                    ),
                                    "fatal"         => array(
                                                        "path"    => $basePath  . '/../logs/',
                                                        "name"    => "exception.log",
                                                        "mode"    => "a",
                                                        "details" => true,
                                                    ),
                                ),
            );
            // load from Zend config ini
            if ( class_exists( 'Zend_Config_Ini' ) && defined( 'APPLICATION_PATH' ) ) {
                static::$_config = new Zend_Config_Ini( APPLICATION_PATH  . '/configs/application.ini' , APPLICATION_ENV );
                // extend the default settings with config values
                static::$_defaults = array_replace_recursive( static::$_defaults , static::$_config->logger->toArray() );
            }
        }
        // for instance specific settings, extend defaults using settings
        $this->_settings = array_replace_recursive( static::$_defaults , $settings );
        return $this;
    }

    /**
     * Call reconfigure to apply a new set of settings extending from defaults
     *
     * @access  public
     * @param   array $settings an array of setting to extend from defaults
     * @return  Codewiz_Logger instance
     */
    public function reconfigure( array $settings = array() )
    {
        $this->_settings = array_replace_recursive( static::$_defaults , $settings );
        return $this;
    }
    
    /**
     * Call applyDefaults to apply default settings
     *
     * @access  public
     * @return  Codewiz_Logger instance
     */
    public function applyDefaults()
    {
        $this->_settings = static::$_defaults;
        return $this;
    }
    
    /**
     * Call registerHandlers from constructor only
     *
     * @access  private
     * @return  Codewiz_Logger instance
     * @internal can only be called once during runtime, via the constructor, best used in Bootstrap
     */
    private function registerHandlers()
    {
        $catchAll = false;
        if ( $this->_settings['enableHandler']['error'] == true && !defined( 'ERROR_HANDLER_ACTIVE' ) ) {
            define('ERROR_HANDLER_ACTIVE', true);
            set_error_handler( array( $this, 'errorHandler' ) );
            $catchAll = true;
        }
        if ( $this->_settings['enableHandler']['exception'] == true && !defined( 'EXCEPTION_HANDLER_ACTIVE' ) ) {
            define('EXCEPTION_HANDLER_ACTIVE', true);
            set_exception_handler( array( $this, 'exceptionHandler' )  );
            $catchAll = true;
        }
        if ( $this->_settings['enableHandler']['fatal'] == true && !defined( 'FATAL_HANDLER_ACTIVE' ) ) {
            define('FATAL_HANDLER_ACTIVE', true);
            register_shutdown_function( array( $this, 'fatalHandler' ) );
            $catchAll = true;
        }
        // Are we going to change the environment so we can catch all?
        if ( $catchAll === true ) {
            ini_set( "display_errors", "off" );
            error_reporting( E_ALL );
        }
        return $this;
    }
    
    /**
     * Call restoreEnvironment if you registered handlers via the constructor
     *
     * @access  public
     * @return  Codewiz_Logger instance
     * @internal Restores display_errors and error_reporting also
     */
    public function restoreEnvironment()
    {
        if ( $this->_settings['restore']['exceptionHandler'] == true && ( defined( 'EXCEPTION_HANDLER_ACTIVE' ) && EXCEPTION_HANDLER_ACTIVE == true ) )
            restore_exception_handler();
        if ( $this->_settings['restore']['errorHandler'] == true && ( defined( 'ERROR_HANDLER_ACTIVE' ) && ERROR_HANDLER_ACTIVE == true ) )
            restore_error_handler();
        if ( $this->_settings['restore']['display_errors'] == true )
            ini_set( "display_errors", static::$_defaults['global']['display_errors'] );
        if ( $this->_settings['restore']['error_reporting'] == true )
            error_reporting( static::$_defaults['global']['error_reporting'] );
        
        return $this;
    }
    
    /**
     * DO NOT Call exceptionHandler() used only by set_exception_handler()
     *
     * @access  public
     */
    public function exceptionHandler( $e )
    {
        ob_start();
        $this->_type = "exception";
        $this->errorStructureAndLog( $e );
        return;
    }
    
    /**
     * DO NOT Call fatalHandler() used only by register_shutdown_function()
     *
     * @access  public
     */
    public function fatalHandler()
    {
        ob_start();
        $error = error_get_last();
        if( $error !== NULL) {
            $this->_type = "fatal";
            $this->errorStructureAndLog( 
                    $error['type'] , 
                    $error['message'] , 
                    $error['file'] , 
                    $error['line']
                );
        }
        return;
    }
    
    /**
     * DO NOT Call errorHandler() used only by set_error_handler()
     *
     * @access  public
     */
    public function errorHandler( $errno , $errstr = '' , $errfile = '' , $errline = '' )
    {
        ob_start();
        // check if error
        if( func_num_args() == 5 ) {
            $this->_type = "error";
            list($errno, $errstr, $errfile, $errline) = func_get_args(); // omit $errcontext
            $this->errorStructureAndLog( $errno, $errstr, $errfile, $errline );
        } else { // caught exception
            $this->_type = "exception";
            $this->errorStructureAndLog( func_get_arg(0) );
        }
        return;
    }
    
    /**
     * Call _buildMessage to return plaintext and html versions of Exception information
     *
     * @access  private
     * @param   array $errorArr expects array( 'errno'=>string , 'errstr'=>string , 'errfile'=>string , 'errline'=>integer , 'backtrace'=>string )
     * @return  array ( 'html'=>string , 'plaintext'=>string , 'data'=>array( 'errno'=>string , 'errstr'=>string , 'errfile'=>string , 'errline'=>integer , 'backtrace'=>string , 'userid'=>integer , 'type'=>string , 'post'=>$_POST , 'get'=>$_GET , 'cookie'=>$_COOKIE , 'server'=>$_SERVER ) )
     * @todo get real user_id
     */
    private function _buildMessage( array $errorArr )
    {
        // create error message string
        $err = array_key_exists( $errorArr['errno'] , static::$_errorType ) ? static::$_errorType[$errorArr['errno']] : 'APPLICATION ERROR';
        // nice error string
        $errMsg = $err.": ".$errorArr['errstr']." in ".$errorArr['errfile']." on line ".$errorArr['errline'];
        // start backtrace
        foreach ( $errorArr['backtrace'] as $v ) 
        {
            if (isset($v['class'])) {
                $trace = $err. ' in class '.$v['class'].'::'.$v['function'].'(';
                if (isset($v['args'])) {
                    $separator = '';
                    foreach($v['args'] as $arg ) {
                        $trace .= "$separator".$this->_getArgument($arg);
                        $separator = ', ';
                    }
                }
                $trace .= ')';
            }
            elseif (isset($v['function']) && empty($trace)) {
                $trace = 'in function '.$v['function'].'(';
                if (!empty($v['args'])) {
                    $separator = '';
                    foreach($v['args'] as $arg ) {
                        $trace .= "$separator".$this->_getArgument($arg);
                        $separator = ', ';
                    }
                }
                $trace .= ')';
            }
        }
        // plaintext email
        $structure['plaintext'] = '*** '.nl2br($errMsg).' ***'.PHP_EOL;
        if ( $this->_settings['emails']['details'] == true ) {
            $structure['plaintext'] .= 'Trace: '.nl2br($trace);
            $structure['plaintext'] .= '---------------------------------------------'.PHP_EOL;
            $structure['plaintext'] .= 'Server Info:'.PHP_EOL.print_r($_SERVER, 1);
            $structure['plaintext'] .= '---------------------------------------------'.PHP_EOL;
            $structure['plaintext'] .= 'COOKIE:'.PHP_EOL.print_r($_COOKIE, 1);
            $structure['plaintext'] .= '---------------------------------------------'.PHP_EOL;
            $structure['plaintext'] .= 'POST:'.PHP_EOL.print_r($_POST, 1);
            $structure['plaintext'] .= '---------------------------------------------'.PHP_EOL;
            $structure['plaintext'] .= 'GET:'.PHP_EOL.print_r($_GET, 1).PHP_EOL;
        }
        // html email
        $structure['html']  = "<strong>".$this->_type." at ".date($this->_settings['dateFormat'])."</strong>";
        $structure['html'] .= "<table><thead><th colspan='2'>".nl2br($errMsg)."</th></thead>";
        $structure['html'] .= "<tbody>";
        $structure['html'] .= "<tr valign='top'><td><b>Error</b></td><td>".$errorArr['errstr']."</td></tr>";
        $structure['html'] .= "<tr valign='top'><td><b>Error No.</b></td><td>".$errorArr['errno']."</td></tr>";
        $structure['html'] .= "<tr valign='top'><td><b>File</b></td><td>".$errorArr['errfile']."</td></tr>";
        $structure['html'] .= "<tr valign='top'><td><b>Line</b></td><td>".$errorArr['errline']."</td></tr>";
        if ( $this->_settings['emails']['details'] == true ) {
            $structure['html'] .= "<tr valign='top'><td><b>Trace</b></td><td><pre>".nl2br(htmlentities(highlight_string($trace)))."</pre></td></tr>";
            $structure['html'] .= "<tr valign='top'><td><b>Server</b></td><td><pre>".print_r($_SERVER, 1)."</pre></td></tr>";
            $structure['html'] .= "<tr valign='top'><td><b>Cookie</b></td><td><pre>".print_r($_COOKIE, 1)."</pre></td></tr>";
            $structure['html'] .= "<tr valign='top'><td><b>POST</b></td><td><pre>".print_r($_POST, 1)."</pre></td></tr>";
            $structure['html'] .= "<tr valign='top'><td><b>GET</b></td><td><pre>".print_r($_GET, 1)."</pre></td></tr>";
        }
        $structure['html'] .= "</tbody></table>";
        
        $structure['style'] = "#outlook a {padding:0;}body{width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0;} strong { font-size: 2em; line-height: 45px; text-transform: uppercase; box-shadow: inset 0 0 5px rgba(53,86,129, 0.5); text-shadow: 0 -1px rgba(0,0,0,0.6); padding: 5px 15px; border-radius: 0 15px 0 15px; border-bottom: 1px solid rgba(53,86,129, 0.3); background: #355681; background: rgba(53,86,129, 0.8); color: #FFF; color: rgb(255,255,255);  } table {width: 100%;border: 0;}table tbody {margin: 0;padding: 0;border: 0;outline: 0;font-size: 100%;vertical-align: baseline;background: transparent;}table thead {text-align: left;}table thead th {background: -moz-linear-gradient(top, #F0F0F0 0, #DBDBDB 100%);background: -webkit-gradient(linear, left top, left bottom, color-stop(0%, #F0F0F0), color-stop(100%, #DBDBDB));filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#F0F0F0', endColorstr='#DBDBDB', GradientType=0);border: 1px solid #B0B0B0;color: #444;font-size: 16px;font-weight: bold;padding: 3px 10px;}table td {padding: 3px 10px;}table tr:nth-child(even) {background: #F2F2F2;}pre {white-space: pre-line;width: 100%;}";
        
        $structure['data'] = array(
                "errno"     => $errorArr['errno'],
                "errstr"    => $errorArr['errstr'],
                "errfile"   => $errorArr['errfile'],
                "errline"   => $errorArr['errline'],
                "backtrace" => $trace,
                "message"   => $errMsg,
                "type"      => $this->_type,
                "userid"   => 0, //@todo get real userid
                "post"      => json_encode( $_POST ),
                "get"       => json_encode( $_GET ),
                "cookie"    => json_encode( $_COOKIE ),
                "server"    => json_encode( $_SERVER ),
            );
        return $structure;
    }
    
    /**
     * Call _logToFile with results from calling _buildMessage()
     *
     * @access  private
     * @param   array $structure results from calling _buildMessage()
     * @return  Codewiz_Logger instance
     */
    private function _logToFile( $structure )
    {
        $prefix = date( $this->_settings['dateFormat'] ) . " - " . strtoupper( $this->_type ) . " --> ";
        $message = $structure['data']['message'];
        if ( $this->_settings['files'][$this->_type]['details'] == true ) {
            $message .= " POST: " . $structure['data']['post'] . " GET: " . $structure['data']['get'] . " COOKIE: " . $structure['data']['cookie'] . " SERVER: " . $structure['data']['server'] . " TRACE: " . $structure['data']['backtrace'];
        }
        $message .= PHP_EOL;
        if ( $this->_settings['files'][$this->_type]['mode'] === "a" ) {
            file_put_contents(
                $this->_settings['files'][$this->_type]['path'] . $this->_settings['files'][strtolower($this->_type)]['name'],
                $prefix . $message,
                FILE_APPEND
            );
        } else {
            $fh = fopen(
                $this->_settings['files'][$this->_type]['path'] . $this->_settings['files'][strtolower($this->_type)]['name'],
                $this->_settings['files'][$this->_type]['mode']
            );
            fwrite( $fh , $prefix . $message );
            fclose( $fh );
        }
        return $this;
    }
    
    /**
     * Call _logToEmail with results from calling _buildMessage()
     *
     * @access  private
     * @param   array $structure results from calling _buildMessage()
     * @return  Codewiz_Logger instance
     */
    private function _logToEmail( $structure )
    {
        if ( !empty( $this->_settings['adminEmail'] ) ) {
            $email = $this->_settings['adminEmail'];
            $subject = 'Critical problem on ' . $_SERVER['HTTP_HOST'];
            $headers = "From:chris@codewiz.biz\r\n";
            if ( $this->_settings['emails']['content'] == "plaintext" ) {
                $body = $structure['plaintext'];
            }
            elseif ( $this->_settings['emails']['content'] == "html" ) {
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
                $body = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
                    <html xmlns='http://www.w3.org/1999/xhtml'><head>
                    <meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
                    <title>" . $subject . "</title>
                    <style type='text/css'>".$structure['style']."</style>
                    <title>" . $subject . "</title></head><body>".$structure['html']."</body></html>";
            }
            elseif ( $this->_settings['emails']['content'] == "multi" ) {
                $boundary = uniqid('np');
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/alternative;boundary=" . $boundary . "\r\n";

                $body = "This is a MIME encoded message.";
                $body .= "\r\n\r\n--" . $boundary . "\r\n";
                $body .= "Content-type: text/plain;charset=utf-8\r\n\r\n";
                $body .= $structure['plaintext'];
                $body .= "\r\n\r\n--" . $boundary . "\r\n";
                $body .= "Content-type: text/html;charset=utf-8\r\n\r\n";
                $body .= $structure['html'];
                $body .= "\r\n\r\n--" . $boundary . "--";
            }
            mail( $email , $subject, $body , $headers );
        }
        return $this;
    }
    
    /**
     * Call _logToDb with results from calling _buildMessage()
     *
     * @access  private
     * @param   array $structure results from calling _buildMessage()
     * @return  Codewiz_Logger instance
     */
    private function _logToDb( $structure )
    {
        if ( !is_object( static::$_db ) )
        static::$_db = new PDO('mysql:host='.static::$_config->resources->db->params->host.';dbname='.static::$_config->resources->db->params->dbname.';charset=utf8', 
                static::$_config->resources->db->params->username, 
                static::$_config->resources->db->params->password
            );
        unset($structure['data']['message']);
        static::$_db->prepare("INSERT INTO `logger`( `type`, `user_id`, `errno`, `errstr`, `errfile`, `errline`, `backtrace`, `post`, `get`, `cookie`, `server`) VALUES ( :type, :userid, :errno, :errstr, :errfile, :errline, :backtrace, :post, :get, :cookie, :server )")->execute( $structure['data'] );
        
        return $this;
    }
    
    /**
     * Call _getArgument for string explaination of variable type
     *
     * @access  private
     * @param   string|boolean|object|array|resource $arg given variable returns a string explaination of variable type
     * @return  string explaination of variable type
     */
    private function _getArgument( $arg )
    {
        switch (strtolower(gettype($arg))) {
            case 'string':
                return( '"'.str_replace( array("\n"), array(''), $arg ).'"' );
            case 'boolean':
                return (bool)$arg;
            case 'object':
                return 'object('.get_class($arg).')';
            case 'array':
                $ret = 'array(';
                $separtor = '';
                foreach ($arg as $k => $v) {
                    $ret .= $separtor.$this->_getArgument($k).' => '.$this->_getArgument($v);
                    $separtor = ', ';
                }
                $ret .= ')';
                return $ret;
            case 'resource':
                return 'resource('.get_resource_type($arg).')';
            default:
                return var_export($arg, true);
        }
    }
    
    /**
     * Call _displaySafeMessage to exit execution and output safe text message
     *
     * @access  private
     * @param   string $err a heading for the safe message, usually error type
     */
    private function _displaySafeMessage( $err = "APPLICATION ERROR" )
    {
        ob_end_clean();
        print( '<h2>'.$err.'</h2><hr>
            We encountered an error and notified the administrator/s.<br />
            Please go back and try again.<br />' );
    }

    /**
     * Call errorStructureAndLog exteranlly with an argument instance of Exception.
     * All calls to errorStructureAndLog will pass on structured $errorArr to _logTo*() Methods
     * 
     * @access  public
     * @param   object $e an instance of Exception
     * @internal    Internally errorStructureAndLog can take 4 arguments.
     * @internalArguments    $type , $message , $errfile , $errline
     * @return  Codewiz_Logger instance
     */
    public function errorStructureAndLog()
    {
        if ( func_num_args() === 1 && is_object( func_get_args(0) ) ) {
            // we recieved an Exception instance
            $e = func_get_args(0);
            $type = $e->getCode();
            $message = $e->getMessage();
            $errfile = $e->getFile();
            $errline = $e->getLine();
            $backtrace = $e->getTrace();
        } elseif ( func_num_args() == 2 ) {
            list( $type , $message ) = func_get_args();
        } elseif ( func_num_args() == 3 ) {
            list( $type , $message , $errfile ) = func_get_args();
        } elseif ( func_num_args() == 4 ) {
            list( $type , $message , $errfile , $errline ) = func_get_args();
        } elseif ( func_num_args() == 5 ) {
            list( $type , $message , $errfile , $errline , $backtrace ) = func_get_args();
        } else {
            // no args, do nothing
            return $this;
        }
        // get backtrace (if available)
        $bt = ( isset( $backtrace ) && !empty( $backtrace ) ? $backtrace : debug_backtrace() ); $caller = array_shift( $bt );
        // set some defaults
        $errfile = ( isset( $errfile ) && !empty( $errfile ) && is_string( $errfile ) ? $errfile : $caller['file'] );
        $errline = ( isset( $errline ) && !empty( $errline ) && is_numeric( $errline ) ? $errline : $caller['line'] );
        $this->_errArr = array(
            "errstr"   => $message,
            "errfile"   => $errfile,
            "errline"   => $errline,
            "backtrace" => array_reverse( $bt ),
        );
        // using our passed in log type, set errno appropriately
        if ( isset( $type ) )
        {
            switch ( strtoupper( $type ) ) {
                case "FATAL":     $this->_errArr['errno'] = E_CORE_ERROR;        break;
                case "EXCEPTION": $this->_errArr['errno'] = E_ERROR;             break;
                case "ERROR":     $this->_errArr['errno'] = E_USER_ERROR;        break;
                case "WARNING":   $this->_errArr['errno'] = E_USER_WARNING;      break;
                case "NOTICE":    $this->_errArr['errno'] = E_NOTICE;            break;
                case "INFO":      $this->_errArr['errno'] = E_USER_NOTICE;       break;
                case "DEBUG":     $this->_errArr['errno'] = E_WARNING;           break;
                default:          $this->_errArr['errno'] = E_RECOVERABLE_ERROR; break;
            }
        }
        // now we should have a full Exception, Log it.
        try {
            $multi = $this->_buildMessage( $this->_errArr );
            foreach ( $this->_settings['destination'][strtolower($this->_type)] as $logType )
            switch ( $logType ) {
                case "file":
                    $this->_logToFile( $multi );
                    break;
                case "db":
                    $this->_logToDb( $multi );
                    break;
                case "email":
                    $this->_logToEmail( $multi );
                    break;
            }
        } catch ( Exception $e ) {
            ob_end_clean();
            if ( APPLICATION_ENV !== 'production' ) {
                print( "<p>".$e->getCode().": ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."</p><pre>Trace: ".$e->getTrace()."<br />SERVER: ".$_SERVER."<br />COOKIE: ".$_COOKIE."<br />GET: ".$_GET."<br />POST: ".$_POST."</pre>" );
            } else {
                $this->_displaySafeMessage( array_key_exists( $e->getCode() , static::$_errorType ) ? static::$_errorType[$e->getCode()] : null );
            }
        } catch ( PDOException $e ) {
            ob_end_clean();
            if ( APPLICATION_ENV !== 'production' ) {
                print( "<p>".$e->getCode().": ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."</p><pre>Trace: ".$e->getTrace()."<br />SERVER: ".$_SERVER."<br />COOKIE: ".$_COOKIE."<br />GET: ".$_GET."<br />POST: ".$_POST."</pre>" );
            } else {
                $this->_displaySafeMessage( array_key_exists( $e->getCode() , static::$_errorType ) ? static::$_errorType[$e->getCode()] : null );
            }
        }
        ob_end_clean();
        return $this;
    }
        
    /**
     * Create a fatal log entry
     *
     * @access  public
     * @param   string  $message a message string for the fatal log entry
     * @param   string  $errfile name of the file the fatal message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the fatal message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function fatal( $message , $errfile = null , $errline = null )
    {
        $this->_type = "fatal";
        $this->errorStructureAndLog( "FATAL" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create an exception log entry
     *
     * @access  public
     * @param   string  $message a message string for the exception log entry
     * @param   string  $errfile name of the file the exception message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the exception message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function exception( $message , $errfile = null , $errline = null )
    {
        $this->_type = "exception";
        $this->errorStructureAndLog( "EXCEPTION" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create an error log entry
     *
     * @access  public
     * @param   string  $message a message string for the error log entry
     * @param   string  $errfile name of the file the error message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the error message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function error( $message , $errfile = null , $errline = null )
    {
        $this->_type = "error";
        $this->errorStructureAndLog( "ERROR" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create a warning log entry
     *
     * @access  public
     * @param   string  $message a message string for the warning log entry
     * @param   string  $errfile name of the file the warning message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the warning message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function warning( $message , $errfile = null , $errline = null )
    {
        $this->_type = "default";
        $this->errorStructureAndLog( "WARNING" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create a notice log entry
     *
     * @access  public
     * @param   string  $message a message string for the notice log entry
     * @param   string  $errfile name of the file the notice message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the notice message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function notice( $message , $errfile = null , $errline = null )
    {
        $this->_type = "default";
        $this->errorStructureAndLog( "NOTICE" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create an info log entry
     *
     * @access  public
     * @param   string  $message a message string for the info log entry
     * @param   string  $errfile name of the file the info message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the info message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function info( $message , $errfile = null , $errline = null )
    {
        $this->_type = "default";
        $this->errorStructureAndLog( "INFO" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * Create a debug log entry
     *
     * @access  public
     * @param   string  $message a message string for the debug log entry
     * @param   string  $errfile name of the file the debug message occured, if a backtrace is available this can be automatically detected
     * @param   integer $errline line number in the file for where the debug message occured, if a backtrace is available this can be automatically detected
     * @return  Codewiz_Logger instance
     */
    public function debug( $message , $errfile = null , $errline = null )
    {
        $this->_type = "debug";
        $this->errorStructureAndLog( "DEBUG" , $message , $errfile , $errline );
        return $this;
    }
    
    /**
     * get email content type from current instance settings
     *
     * @access  public
     * @return  string plaintext|html|multi Email Content Type from current instance settings
     */
    public function getEmailContentType()
    {
        return $this->_settings['emails']['content'];
    }
    
    /**
     * set email content type for current instance settings
     *
     * @access  public
     * @param string $contentType plaintext|html|multi Email Content Type for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setEmailContentType( $contentType )
    {
        $allowed = array("plaintext","html","multi");
  	if ( in_array( $contentType , $allowed ) ) $this->_settings['emails']['content'] = $contentType;
        return $this;
    }
    
    /**
     * get email content type from default settings
     *
     * @access  public
     * @return  string plaintext|html|multi Email Content Type from current instance settings
     */
    public function getEmailContentTypeDefault()
    {
        return static::$_defaults['emails']['content'];
    }
    
    /**
     * set email content type for default settings
     *
     * @access  public
     * @param string $contentType plaintext|html|multi Email Content Type for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setEmailContentTypeDefault( $contentType )
    {
        $allowed = array("plaintext","html","multi");
	if ( in_array( $contentType , $allowed ) ) static::$_defaults['emails']['content'] = $contentType;
        return $this;
    }
    
    /**
     * get Include Details In Emails from current instance settings
     *
     * @access  public
     * @return  bool true|false Include Details In Emails from current instance settings
     */
    public function getIncludeDetailsInEmails()
    {
        return $this->_settings['emails']['details'];
    }
    
    /**
     * set Include Details In Emails for current instance settings
     *
     * @access  public
     * @param bool $includeDetails true|false Include Details In Emails for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setIncludeDetailsInEmails( $includeDetails )
    {
	$this->_settings['emails']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Include Details In Emails from default settings
     *
     * @access  public
     * @return  bool true|false Include Details In Emails from current instance settings
     */
    public function getIncludeDetailsInEmailsDefault()
    {
        return static::$_defaults['emails']['details'];
    }
    
    /**
     * set Include Details In Emails for default settings
     *
     * @access  public
     * @param bool $includeDetails true|false Include Details In Emails for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setIncludeDetailsInEmailsDefault( $includeDetails )
    {
	static::$_defaults['emails']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Admin Email from current instance settings
     *
     * @access  public
     * @return  string adminEmail from current instance settings
     */
    public function getAdminEmail()
    {
        return $this->_settings['adminEmail'];
    }
    
    /**
     * set Admin Email for current instance settings
     *
     * @access  public
     * @param string $adminEmail destination for log emails
     * @return  Codewiz_Logger instance
     */
    public function setAdminEmail( $adminEmail )
    {
        $this->_settings['adminEmail'] = $adminEmail;
        return $this;
    }
    
    /**
     * get Admin Email from default settings
     *
     * @access  public
     * @return  string adminEmail from default settings
     */
    public function getAdminEmailDefault()
    {
        return static::$_defaults['adminEmail'];
    }
    
    /**
     * set Admin Email for default settings
     *
     * @access  public
     * @param string $adminEmail default destination for log emails
     * @return  Codewiz_Logger instance
     */
    public function setAdminEmailDefault( $adminEmail )
    {
        static::$_defaults['adminEmail'] = $adminEmail;
        return $this;
    }
    
    /**
     * get Date Format from current instance settings
     *
     * @access  public
     * @return  string dateFormat from current instance settings
     */
    public function getDateFormat()
    {
        return $this->_settings['dateFormat'];
    }
    
    /**
     * set Admin Email for current instance settings
     *
     * @access  public
     * @param string $dateFormat date format mask used in logs
     * @return  Codewiz_Logger instance
     */
    public function setDateFormat( $dateFormat )
    {
        $this->_settings['dateFormat'] = $dateFormat;
        return $this;
    }
    
    /**
     * get Date Format from default settings
     *
     * @access  public
     * @return  string dateFormat from default settings
     */
    public function getDateFormatDefault()
    {
        return static::$_defaults['dateFormat'];
    }
    
    /**
     * set Admin Email for default settings
     *
     * @access  public
     * @param   string $dateFormat default date format mask used in logs
     * @return  Codewiz_Logger instance
     */
    public function setDateFormatDefault( $dateFormat )
    {
        static::$_defaults['dateFormat'] = $dateFormat;
        return $this;
    }
    
    /**
     * get Enable Error Handler from current instance settings
     *
     * @access  public
     * @return  bool errorEnableHandler from current instance settings
     */
    public function getErrorEnableHandler()
    {
        return $this->_settings['enableHandler']['error'];
    }
    
    /**
     * set Enable Error Handler for current instance settings
     *
     * @access  public
     * @param   bool true|false to enable Error Handler
     * @return  Codewiz_Logger instance
     */
    public function setErrorEnableHandler( $errorEnableHandler )
    {
        $this->_settings['enableHandler']['error'] = $errorEnableHandler;
        return $this;
    }
    
    /**
     * get Enable Error Handler from default settings
     *
     * @access  public
     * @return  bool errorEnableHandler from default settings
     */
    public function getErrorEnableHandlerDefault()
    {
        return static::$_defaults['enableHandler']['error'];
    }
    
    /**
     * set Enable Error Handler for default settings
     *
     * @access  public
     * @param   bool true|false to enable Error Handler
     * @return  Codewiz_Logger instance
     */
    public function setErrorEnableHandlerDefault( $errorEnableHandler )
    {
        static::$_defaults['enableHandler']['error'] = $errorEnableHandler;
        return $this;
    }
    
    /**
     * get Enable Exception Handler from current instance settings
     *
     * @access  public
     * @return  bool exceptionEnableHandler from current instance settings
     */
    public function getExceptionEnableHandler()
    {
        return $this->_settings['enableHandler']['exception'];
    }
    
    /**
     * set Enable Exception Handler for current instance settings
     *
     * @access  public
     * @param   bool true|false to enable Exception Handler
     * @return  Codewiz_Logger instance
     */
    public function setExceptionEnableHandler( $exceptionEnableHandler )
    {
        $this->_settings['enableHandler']['exception'] = $exceptionEnableHandler;
        return $this;
    }
    
    /**
     * get Enable Exception Handler from default settings
     *
     * @access  public
     * @return  bool exceptionEnableHandler from default settings
     */
    public function getExceptionEnableHandlerDefault()
    {
        return static::$_defaults['enableHandler']['exception'];
    }
    
    /**
     * set Enable Exception Handler for default settings
     *
     * @access  public
     * @param   bool true|false to enable Exception Handler
     * @return  Codewiz_Logger instance
     */
    public function setExceptionEnableHandlerDefault( $exceptionEnableHandler )
    {
        static::$_defaults['enableHandler']['exception'] = $exceptionEnableHandler;
        return $this;
    }
    
    /**
     * get Enable Fatal Handler from current instance settings
     *
     * @access  public
     * @return  bool fatalEnableHandler from current instance settings
     */
    public function getFatalEnableHandler()
    {
        return $this->_settings['enableHandler']['fatal'];
    }
    
    /**
     * set Enable Fatal Handler for current instance settings
     *
     * @access  public
     * @param   bool true|false to enable Fatal Handler
     * @return  Codewiz_Logger instance
     */
    public function setFatalEnableHandler( $fatalEnableHandler )
    {
        $this->_settings['enableHandler']['fatal'] = $fatalEnableHandler;
        return $this;
    }
    
    /**
     * get Enable Fatal Handler from default settings
     *
     * @access  public
     * @return  bool fatalEnableHandler from default settings
     */
    public function getFatalEnableHandlerDefault()
    {
        return static::$_defaults['enableHandler']['fatal'];
    }
    
    /**
     * set Enable Fatal Handler for default settings
     *
     * @access  public
     * @param   bool true|false to enable Fatal Handler
     * @return  Codewiz_Logger instance
     */
    public function setFatalEnableHandlerDefault( $fatalEnableHandler )
    {
        static::$_defaults['enableHandler']['fatal'] = $fatalEnableHandler;
        return $this;
    }
    
    /**
     * get global display_errors from current instance settings
     *
     * @access  public
     * @return  string|integer display_errors from current instance settings
     */
    public function getGlobalDisplayErrors()
    {
        return $this->_settings['global']['display_errors'];
    }
    
    /**
     * set display_errors for current instance settings
     *
     * @access  public
     * @param   integer|string display_errors value
     * @return  Codewiz_Logger instance
     */
    public function setGlobalDisplayErrors( $globalDisplayErrors )
    {
        $this->_settings['global']['display_errors'] = $globalDisplayErrors;
        return $this;
    }
    
    /**
     * get global display_errors from default settings
     *
     * @access  public
     * @return  string|integer display_errors from default settings
     */
    public function getGlobalDisplayErrorsDefault()
    {
        return static::$_defaults['global']['display_errors'];
    }
    
    /**
     * set display_errors for default settings
     *
     * @access  public
     * @param   integer|string display_errors value
     * @return  Codewiz_Logger instance
     */
    public function setGlobalDisplayErrorsDefault( $globalDisplayErrors )
    {
        static::$_defaults['global']['display_errors'] = $globalDisplayErrors;
        return $this;
    }
    
    /**
     * get global error_reporting from current instance settings
     *
     * @access  public
     * @return  string|integer error_reporting from current instance settings
     */
    public function getGlobalErrorReporting()
    {
        return $this->_settings['global']['error_reporting'];
    }
    
    /**
     * set global error_reporting for current instance settings
     *
     * @access  public
     * @param  string|integer error_reporting for current instance settings
     * @return Codewiz_Logger instance
     */
    public function setGlobalErrorReporting( $globalErrorReporting )
    {
        $this->_settings['global']['error_reporting'] = $globalErrorReporting;
        return $this;
    }
    
    /**
     * get global error_reporting from default settings
     *
     * @access  public
     * @return  string|integer error_reporting from default settings
     */
    public function getGlobalErrorReportingDefault()
    {
        return static::$_defaults['global']['error_reporting'];
    }
    
    /**
     * set global error_reporting for default settings
     *
     * @access  public
     * @param  string|integer error_reporting for default settings
     * @return Codewiz_Logger instance
     */
    public function setGlobalErrorReportingDefault( $globalErrorReporting )
    {
        static::$_defaults['global']['error_reporting'] = $globalErrorReporting;
        return $this;
    }
    
    /**
     * get default log to file|db|email from current instance settings
     *
     * @access  public
     * @return  array values for default log to file|db|email from current instance settings
     */
    public function getDefaultDestination()
    {
        return $this->_settings['destination']['default'];
    }
    
    /**
     * set default log to file|db|email from current instance settings
     *
     * @access  public
     * @param   array|string array values or a single value for default log to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setDefaultDestination( $defaultDestination )
    {
		if ( is_string( $defaultDestination ) ) 
			$this->_settings['destination']['default'][] = $defaultDestination;
		elseif ( is_array( $defaultDestination ) )
			$this->_settings['destination']['default'] = $defaultDestination;
        return $this;
    }
    
    /**
     * remove default log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log default to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeDefaultDestination( $defaultDestination )
    {
        if ( is_string( $defaultDestination ) && ( $key = array_search( $defaultDestination , $this->_settings['destination']['default'] ) ) !== false )
                unset($this->_settings['destination']['default'][$key]);
        return $this;
    }
    
    /**
     * get debug log to file|db|email from current instance settings
     *
     * @access  public
     * @return  array values for debug log to file|db|email from current instance settings
     */
    public function getDebugDestination()
    {
        return $this->_settings['destination']['debug'];
    }
    
    /**
     * set debug log to file|db|email from current instance settings
     *
     * @access  public
     * @param   array|string array values or a single value for debug log to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setDebugDestination( $debugDestination )
    {
		if ( is_string( $debugDestination ) ) 
			$this->_settings['destination']['debug'][] = $debugDestination;
		elseif ( is_array( $debugDestination ) )
			$this->_settings['destination']['debug'] = $debugDestination;
        return $this;
    }
    
    /**
     * remove debug log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log debug to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeDebugDestination( $debugDestination )
    {
        if ( is_string( $debugDestination ) && ( $key = array_search( $debugDestination , $this->_settings['destination']['debug'] ) ) !== false )
                unset($this->_settings['destination']['debug'][$key]);
        return $this;
    }
    
    /**
     * get error to file|db|email from current instance settings
     *
     * @access  public
     * @return  array values for error to file|db|email from current instance settings
     */
    public function getErrorDestination()
    {
        return $this->_settings['destination']['error'];
    }
    
    /**
     * set error log to file|db|email from current instance settings
     *
     * @access  public
     * @param   array|string array values or a single value for error log to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setErrorDestination( $errorDestination )
    {
		if ( is_string( $errorDestination ) ) 
			$this->_settings['destination']['error'][] = $errorDestination;
		elseif ( is_array( $errorDestination ) )
			$this->_settings['destination']['error'] = $errorDestination;
        return $this;
    }
    
    /**
     * remove error log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log error to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeErrorDestination( $errorDestination )
    {
        if ( is_string( $errorDestination ) && ( $key = array_search( $errorDestination , $this->_settings['destination']['error'] ) ) !== false )
                unset($this->_settings['destination']['error'][$key]);
        return $this;
    }
    
    /**
     * get exception to file|db|email from current instance settings
     *
     * @access  public
     * @return  array values for exception to file|db|email from current instance settings
     */
    public function getExceptionDestination()
    {
        return $this->_settings['destination']['exception'];
    }
    
    /**
     * set exception log to file|db|email from current instance settings
     *
     * @access  public
     * @param   array|string array values or a single value for exception log to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setExceptionDestination( $exceptionDestination )
    {
		if ( is_string( $exceptionDestination ) ) 
			$this->_settings['destination']['exception'][] = $exceptionDestination;
		elseif ( is_array( $exceptionDestination ) )
			$this->_settings['destination']['exception'] = $exceptionDestination;
        return $this;
    }
    
    /**
     * remove exception log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log exception to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeExceptionDestination( $exceptionDestination )
    {
        if ( is_string( $exceptionDestination ) && ( $key = array_search( $exceptionDestination , $this->_settings['destination']['exception'] ) ) !== false )
                unset($this->_settings['destination']['exception'][$key]);
        return $this;
    }
    
    /**
     * get fatal to file|db|email from current instance settings
     *
     * @access  public
     * @return  array values for fatal to file|db|email from current instance settings
     */
    public function getFatalDestination()
    {
        return $this->_settings['destination']['fatal'];
    }
    
    /**
     * set fatal log to file|db|email from current instance settings
     *
     * @access  public
     * @param   array|string array values or a single value for fatal log to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function setFatalDestination( $fatalDestination )
    {
		if ( is_string( $fatalDestination ) ) 
			$this->_settings['destination']['fatal'][] = $fatalDestination;
		elseif ( is_array( $fatalDestination ) )
			$this->_settings['destination']['fatal'] = $fatalDestination;
        return $this;
    }
    
    /**
     * remove fatal log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log fatal to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeFatalDestination( $fatalDestination )
    {
        if ( is_string( $fatalDestination ) && ( $key = array_search( $fatalDestination , $this->_settings['destination']['fatal'] ) ) !== false )
                unset($this->_settings['destination']['fatal'][$key]);
        return $this;
    }
    
    /**
     * get default to file|db|email from default settings
     *
     * @access  public
     * @return  array values for default to file|db|email from default settings
     */
    public function getDefaultDestinationDefault()
    {
        return static::$_defaults['destination']['default'];
    }
    
    /**
     * set default log to file|db|email from default settings
     *
     * @access  public
     * @param   array|string array values or a single value for default log to file|db|email for default settings
     * @return  Codewiz_Logger instance
     */
    public function setDefaultDestinationDefault( $defaultDestination )
    {
		if ( is_string( $defaultDestination ) ) 
			static::$_defaults['destination']['default'][] = $defaultDestination;
		elseif ( is_array( $defaultDestination ) )
			static::$_defaults['destination']['default'] = $defaultDestination;
        return $this;
    }
    
    /**
     * remove default log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log default to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeDefaultDestinationDefault( $defaultDestination )
    {
        if ( is_string( $defaultDestination ) && ( $key = array_search( $defaultDestination , static::$_defaults['destination']['default'] ) ) !== false )
                unset(static::$_defaults['destination']['default'][$key]);
        return $this;
    }
    
    /**
     * get debug to file|db|email from default settings
     *
     * @access  public
     * @return  array values for debug to file|db|email from default settings
     */
    public function getDebugDestinationDefault()
    {
        return static::$_defaults['destination']['debug'];
    }
    
    /**
     * set debug log to file|db|email from default settings
     *
     * @access  public
     * @param   array|string array values or a single value for debug log to file|db|email for default settings
     * @return  Codewiz_Logger instance
     */
    public function setDebugDestinationDefault( $debugDestination )
    {
		if ( is_string( $debugDestination ) ) 
			static::$_defaults['destination']['debug'][] = $debugDestination;
		elseif ( is_array( $debugDestination ) )
			static::$_defaults['destination']['debug'] = $debugDestination;
        return $this;
    }
    
    /**
     * remove debug log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log debug to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeDebugDestinationDefault( $debugDestination )
    {
        if ( is_string( $debugDestination ) && ( $key = array_search( $debugDestination , static::$_defaults['destination']['debug'] ) ) !== false )
                unset(static::$_defaults['destination']['debug'][$key]);
        return $this;
    }
    
    /**
     * get error to file|db|email from default settings
     *
     * @access  public
     * @return  array values for error to file|db|email from default settings
     */
    public function getErrorDestinationDefault()
    {
        return static::$_defaults['destination']['error'];
    }
    
    /**
     * set error log to file|db|email from default settings
     *
     * @access  public
     * @param   array|string array values or a single value for error log to file|db|email for default settings
     * @return  Codewiz_Logger instance
     */
    public function setErrorDestinationDefault( $errorDestination )
    {
		if ( is_string( $errorDestination ) ) 
			static::$_defaults['destination']['error'][] = $errorDestination;
		elseif ( is_array( $errorDestination ) )
			static::$_defaults['destination']['error'] = $errorDestination;
        return $this;
    }
    
    /**
     * remove error log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log error to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeErrorDestinationDefault( $errorDestination )
    {
        if ( is_string( $errorDestination ) && ( $key = array_search( $errorDestination , static::$_defaults['destination']['error'] ) ) !== false )
                unset(static::$_defaults['destination']['error'][$key]);
        return $this;
    }
    
    /**
     * get exception to file|db|email from default settings
     *
     * @access  public
     * @return  array values for exception to file|db|email from default settings
     */
    public function getExceptionDestinationDefault()
    {
        return static::$_defaults['destination']['exception'];
    }
    
    /**
     * set exception log to file|db|email from default settings
     *
     * @access  public
     * @param   array|string array values or a single value for exception log to file|db|email for default settings
     * @return  Codewiz_Logger instance
     */
    public function setExceptionDestinationDefault( $exceptionDestination )
    {
		if ( is_string( $exceptionDestination ) ) 
			static::$_defaults['destination']['exception'][] = $exceptionDestination;
		elseif ( is_array( $exceptionDestination ) )
			static::$_defaults['destination']['exception'] = $exceptionDestination;
        return $this;
    }
    
    /**
     * remove exception log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log exception to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeExceptionDestinationDefault( $exceptionDestination )
    {
        if ( is_string( $exceptionDestination ) && ( $key = array_search( $exceptionDestination , static::$_defaults['destination']['exception'] ) ) !== false )
                unset(static::$_defaults['destination']['exception'][$key]);
        return $this;
    }
    
    /**
     * get fatal to file|db|email from default settings
     *
     * @access  public
     * @return  array values for fatal to file|db|email from default settings
     */
    public function getFatalDestinationDefault()
    {
        return static::$_defaults['destination']['fatal'];
    }
    
    /**
     * set fatal log to file|db|email from default settings
     *
     * @access  public
     * @param   array|string array values or a single value for fatal log to file|db|email for default settings
     * @return  Codewiz_Logger instance
     */
    public function setFatalDestinationDefault( $fatalDestination )
    {
		if ( is_string( $fatalDestination ) ) 
			static::$_defaults['destination']['fatal'][] = $fatalDestination;
		elseif ( is_array( $fatalDestination ) )
			static::$_defaults['destination']['fatal'] = $fatalDestination;
        return $this;
    }
    
    /**
     * remove fatal log to file|db|email from current instance settings
     *
     * @access  public
     * @param   string A single value to remove from log fatal to file|db|email for current instance settings
     * @return  Codewiz_Logger instance
     */
    public function removeFatalDestinationDefault( $fatalDestination )
    {
        if ( is_string( $fatalDestination ) && ( $key = array_search( $fatalDestination , static::$_defaults['destination']['fatal'] ) ) !== false )
                unset(static::$_defaults['destination']['fatal'][$key]);
        return $this;
    }
    
    /**
     * get Debug File Path from current instance settings
     *
     * @access  public
     * @return  string path from current instance settings for Debug logs
     */
    public function getDebugFilePath()
    {
        return $this->_settings['files']['debug']['path'];
    }
    
    /**
     * set Debug File Path from current instance settings
     *
     * @access  public
     * @param string $debugFilePath path from current instance settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFilePath( $debugFilePath )
    {
        $this->_settings['files']['debug']['path'] = $debugFilePath;
        return $this;
    }
    
    /**
     * get Debug File Path from default settings
     *
     * @access  public
     * @return  string path from default settings for Debug logs
     */
    public function getDebugFilePathDefault()
    {
        return static::$_defaults['files']['debug']['path'];
    }
    
    /**
     * set Debug File Path from defaulte settings
     *
     * @access  public
     * @param string $debugFilePath path from default settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFilePathDefault( $debugFilePath )
    {
        static::$_defaults['files']['debug']['path'] = $debugFilePath;
        return $this;
    }
    
    /**
     * get Debug Filename from current instance settings
     *
     * @access  public
     * @return  string file name from current instance settings for Debug logs
     */
    public function getDebugFileName()
    {
        return $this->_settings['files']['debug']['name'];
    }
    
    /**
     * set Debug Filename from current instance settings
     *
     * @access  public
     * @param string $debugFileName file name from current instance settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFileName( $debugFileName )
    {
        $this->_settings['files']['debug']['name'] = $debugFileName;
        return $this;
    }
    
    /**
     * get Debug Filename from default settings
     *
     * @access  public
     * @return  string file name from default settings for Debug logs
     */
    public function getDebugFileNameDefault()
    {
        return static::$_defaults['files']['debug']['name'];
    }
    
    /**
     * set Debug Filename from default settings
     *
     * @access  public
     * @param string $debugFileName file name from default settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFileNameDefault( $debugFileName )
    {
        static::$_defaults['files']['debug']['name'] = $debugFileName;
        return $this;
    }
    
    /**
     * get Debug File Mode from current instance settings
     *
     * @access  public
     * @return  string file mode from current instance settings for Debug logs
     */
    public function getDebugFileMode()
    {
        return $this->_settings['files']['debug']['mode'];
    }
    
    /**
     * set Debug File mode from current instance settings
     *
     * @access  public
     * @param string $debugFileMode file mode from current instance settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFileMode( $debugFileMode )
    {
        $this->_settings['files']['debug']['mode'] = $debugFileMode;
        return $this;
    }
    
    /**
     * get Debug File Mode from default settings
     *
     * @access  public
     * @return  string file mode from default settings for Debug logs
     */
    public function getDebugFileModeDefault()
    {
        return static::$_defaults['files']['debug']['mode'];
    }
    
    /**
     * set Debug File mode from default settings
     *
     * @access  public
     * @param string $debugFileMode file mode from default settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setDebugFileModeDefault( $debugFileMode )
    {
        static::$_defaults['files']['debug']['mode'] = $debugFileMode;
        return $this;
    }
    
    /**
     * get Include Details for Debug logs from current instance settings
     *
     * @access  public
     * @return  bool true|false Include Details for Debug logs from current instance settings for Debug logs
     */
    public function getIncludeDetailsInDebugLogs()
    {
        return $this->_settings['files']['debug']['details'];
    }
    
    /**
     * set Include Details for Debug logs for current instance settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Debug logs from current instance settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInDebugLogs( $includeDetails )
    {
        $this->_settings['files']['debug']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Include Details for Debug logs from default settings
     *
     * @access  public
     * @return  bool true|false Include Details for Debug logs from default settings for Debug logs
     */
    public function getIncludeDetailsInDebugLogsDefault()
    {
        return static::$_defaults['files']['debug']['details'];
    }
    
    /**
     * set Include Details for Debug logs for default settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Debug logs from default settings for Debug logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInDebugLogsDefault( $includeDetails )
    {
        static::$_defaults['files']['debug']['details'] = $includeDetails;
        return $this;
    }

    /**
     * get Error File Path from current instance settings
     *
     * @access  public
     * @return  string file path from current instance settings for Error logs
     */
    public function getErrorFilePath()
    {
        return $this->_settings['files']['error']['path'];
    }
    
    /**
     * set Error File Path from current instance settings
     *
     * @access  public
     * @param  string $errorFilePath file path from current instance settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFilePath( $errorFilePath )
    {
        $this->_settings['files']['error']['path'] = $errorFilePath;
        return $this;
    }
    
    /**
     * get Error File Path from default settings
     *
     * @access  public
     * @return  string file path from default settings for Error logs
     */
    public function getErrorFilePathDefault()
    {
        return static::$_defaults['files']['error']['path'];
    }
    
    /**
     * set Error File Path from default settings
     *
     * @access  public
     * @param  string $errorFilePath file path from default settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFilePathDefault( $errorFilePath )
    {
        static::$_defaults['files']['error']['path'] = $errorFilePath;
        return $this;
    }
    
    /**
     * get Error File Name from current instance settings
     *
     * @access  public
     * @return  string file name from current instance settings for Error logs
     */
    public function getErrorFileName()
    {
        return $this->_settings['files']['error']['name'];
    }
    
    /**
     * set Error File Name from current instance settings
     *
     * @access  public
     * @param  string $errorFileName file name from current instance settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFileName( $errorFileName )
    {
        $this->_settings['files']['error']['name'] = $errorFileName;
        return $this;
    }
    
    /**
     * get Error File Name from default settings
     *
     * @access  public
     * @return  string file name from default settings for Error logs
     */
    public function getErrorFileNameDefault()
    {
        return static::$_defaults['files']['error']['name'];
    }
    
    /**
     * set Error File Name from default settings
     *
     * @access  public
     * @param  string $errorFileName file name from default settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFileNameDefault( $errorFileName )
    {
        static::$_defaults['files']['error']['name'] = $errorFileName;
        return $this;
    }
    
    /**
     * get Error File Mode from current instance settings
     *
     * @access  public
     * @return  string file mode from current instance settings for Error logs
     */
    public function getErrorFileMode()
    {
        return $this->_settings['files']['error']['mode'];
    }
    
    /**
     * set Error File mode from current instance settings
     *
     * @access  public
     * @param string $errorFileMode file mode from current instance settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFileMode( $errorFileMode )
    {
        $this->_settings['files']['error']['mode'] = $errorFileMode;
        return $this;
    }
    
    /**
     * get Error File Mode from default settings
     *
     * @access  public
     * @return  string file mode from default settings for Error logs
     */
    public function getErrorFileModeDefault()
    {
        return static::$_defaults['files']['error']['mode'];
    }
    
    /**
     * set Error File mode from default settings
     *
     * @access  public
     * @param string $errorFileMode file mode from default settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setErrorFileModeDefault( $errorFileMode )
    {
        static::$_defaults['files']['error']['mode'] = $errorFileMode;
        return $this;
    }

    /**
     * get Include Details for Error logs from current instance settings
     *
     * @access  public
     * @return  bool true|false Include Details for Error logs from current instance settings for Error logs
     */
    public function getIncludeDetailsInErrorLogs()
    {
        return $this->_settings['files']['error']['details'];
    }
    
    /**
     * set Include Details for Error logs for current instance settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Error logs from current instance settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInErrorLogs( $includeDetails )
    {
        $this->_settings['files']['error']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Include Details for Error logs from default settings
     *
     * @access  public
     * @return  bool true|false Include Details for Error logs from default settings for Error logs
     */
    public function getIncludeDetailsInErrorLogsDefault()
    {
        return static::$_defaults['files']['error']['details'];
    }
    
    /**
     * set Include Details for Error logs for default settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Error logs from default settings for Error logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInErrorLogsDefault( $includeDetails )
    {
        static::$_defaults['files']['error']['details'] = $includeDetails;
        return $this;
    }

    /**
     * get Exception File Path from current instance settings
     *
     * @access  public
     * @return  string file path from current instance settings for Exception logs
     */
    public function getExceptionFilePath()
    {
        return $this->_settings['files']['exception']['path'];
    }
    
    /**
     * set Exception File Path from current instance settings
     *
     * @access  public
     * @param  string $exceptionFilePath file path from current instance settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFilePath( $exceptionFilePath )
    {
        $this->_settings['files']['exception']['path'] = $exceptionFilePath;
        return $this;
    }
    
    /**
     * get Exception File Path from default settings
     *
     * @access  public
     * @return  string file path from default settings for Exception logs
     */
    public function getExceptionFilePathDefault()
    {
        return static::$_defaults['files']['exception']['path'];
    }
    
    /**
     * set Exception File Path from default settings
     *
     * @access  public
     * @param  string $exceptionFilePath file path from default settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFilePathDefault( $exceptionFilePath )
    {
        static::$_defaults['files']['exception']['path'] = $exceptionFilePath;
        return $this;
    }
    
    /**
     * get Exception File Name from current instance settings
     *
     * @access  public
     * @return  string file name from current instance settings for Exception logs
     */
    public function getExceptionFileName()
    {
        return $this->_settings['files']['exception']['name'];
    }
    
    /**
     * set Exception File Name from current instance settings
     *
     * @access  public
     * @param  string $exceptionFileName file name from current instance settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFileName( $exceptionFileName )
    {
        $this->_settings['files']['exception']['name'] = $exceptionFileName;
        return $this;
    }
    
    /**
     * get Exception File Name from default settings
     *
     * @access  public
     * @return  string file name from default settings for Exception logs
     */
    public function getExceptionFileNameDefault()
    {
        return static::$_defaults['files']['exception']['name'];
    }
    
    /**
     * set Exception File Name from default settings
     *
     * @access  public
     * @param  string $exceptionFileName file name from default settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFileNameDefault( $exceptionFileName )
    {
        static::$_defaults['files']['exception']['name'] = $exceptionFileName;
        return $this;
    }
    
    /**
     * get Exception File Mode from current instance settings
     *
     * @access  public
     * @return  string file mode from current instance settings for Exception logs
     */
    public function getExceptionFileMode()
    {
        return $this->_settings['files']['exception']['mode'];
    }
    
    /**
     * set Exception File mode from current instance settings
     *
     * @access  public
     * @param string $exceptionFileMode file mode from current instance settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFileMode( $exceptionFileMode )
    {
        $this->_settings['files']['exception']['mode'] = $exceptionFileMode;
        return $this;
    }
    
    /**
     * get Exception File Mode from default settings
     *
     * @access  public
     * @return  string file mode from default settings for Exception logs
     */
    public function getExceptionFileModeDefault()
    {
        return static::$_defaults['files']['exception']['mode'];
    }
    
    /**
     * set Exception File mode from default settings
     *
     * @access  public
     * @param string $exceptionFileMode file mode from default settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setExceptionFileModeDefault( $exceptionFileMode )
    {
        static::$_defaults['files']['exception']['mode'] = $exceptionFileMode;
        return $this;
    }

    /**
     * get Include Details for Exception logs from current instance settings
     *
     * @access  public
     * @return  bool true|false Include Details for Exception logs from current instance settings for Exception logs
     */
    public function getIncludeDetailsInExceptionLogs()
    {
        return $this->_settings['files']['exception']['details'];
    }
    
    /**
     * set Include Details for Exception logs for current instance settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Exception logs from current instance settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInExceptionLogs( $includeDetails )
    {
        $this->_settings['files']['exception']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Include Details for Exception logs from default settings
     *
     * @access  public
     * @return  bool true|false Include Details for Exception logs from default settings for Exception logs
     */
    public function getIncludeDetailsInExceptionLogsDefault()
    {
        return static::$_defaults['files']['exception']['details'];
    }
    
    /**
     * set Include Details for Exception logs for default settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Exception logs from default settings for Exception logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInExceptionLogsDefault( $includeDetails )
    {
        static::$_defaults['files']['exception']['details'] = $includeDetails;
        return $this;
    }

    /**
     * get Fatal File Path from current instance settings
     *
     * @access  public
     * @return  string file path from current instance settings for Fatal logs
     */
    public function getFatalFilePath()
    {
        return $this->_settings['files']['fatal']['path'];
    }
    
    /**
     * set Fatal File Path from current instance settings
     *
     * @access  public
     * @param  string $fatalFilePath file path from current instance settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFilePath( $fatalFilePath )
    {
        $this->_settings['files']['fatal']['path'] = $fatalFilePath;
        return $this;
    }
    
    /**
     * get Fatal File Path from default settings
     *
     * @access  public
     * @return  string file path from default settings for Fatal logs
     */
    public function getFatalFilePathDefault()
    {
        return static::$_defaults['files']['fatal']['path'];
    }
    
    /**
     * set Fatal File Path from default settings
     *
     * @access  public
     * @param  string $fatalFilePath file path from default settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFilePathDefault( $fatalFilePath )
    {
        static::$_defaults['files']['fatal']['path'] = $fatalFilePath;
        return $this;
    }
    
    /**
     * get Fatal File Name from current instance settings
     *
     * @access  public
     * @return  string file name from current instance settings for Fatal logs
     */
    public function getFatalFileName()
    {
        return $this->_settings['files']['fatal']['name'];
    }
    
    /**
     * set Fatal File Name from current instance settings
     *
     * @access  public
     * @param  string $fatalFileName file name from current instance settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFileName( $fatalFileName )
    {
        $this->_settings['files']['fatal']['name'] = $fatalFileName;
        return $this;
    }
    
    /**
     * get Fatal File Name from default settings
     *
     * @access  public
     * @return  string file name from default settings for Fatal logs
     */
    public function getFatalFileNameDefault()
    {
        return static::$_defaults['files']['fatal']['name'];
    }
    
    /**
     * set Fatal File Name from default settings
     *
     * @access  public
     * @param  string $fatalFileName file name from default settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFileNameDefault( $fatalFileName )
    {
        static::$_defaults['files']['fatal']['name'] = $fatalFileName;
        return $this;
    }
    
    /**
     * get Fatal File Mode from current instance settings
     *
     * @access  public
     * @return  string file mode from current instance settings for Fatal logs
     */
    public function getFatalFileMode()
    {
        return $this->_settings['files']['fatal']['mode'];
    }
    
    /**
     * set Fatal File mode from current instance settings
     *
     * @access  public
     * @param string $fatalFileMode file mode from current instance settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFileMode( $fatalFileMode )
    {
        $this->_settings['files']['fatal']['mode'] = $fatalFileMode;
        return $this;
    }
    
    /**
     * get Fatal File Mode from default settings
     *
     * @access  public
     * @return  string file mode from default settings for Fatal logs
     */
    public function getFatalFileModeDefault()
    {
        return static::$_defaults['files']['fatal']['mode'];
    }
    
    /**
     * set Fatal File mode from default settings
     *
     * @access  public
     * @param string $fatalFileMode file mode from default settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setFatalFileModeDefault( $fatalFileMode )
    {
        static::$_defaults['files']['fatal']['mode'] = $fatalFileMode;
        return $this;
    }

    /**
     * get Include Details for Fatal logs from current instance settings
     *
     * @access  public
     * @return  bool true|false Include Details for Fatal logs from current instance settings for Fatal logs
     */
    public function getIncludeDetailsInFatalLogs()
    {
        return $this->_settings['files']['fatal']['details'];
    }
    
    /**
     * set Include Details for Fatal logs for current instance settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Fatal logs from current instance settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInFatalLogs( $includeDetails )
    {
        $this->_settings['files']['fatal']['details'] = $includeDetails;
        return $this;
    }
    
    /**
     * get Include Details for Fatal logs from default settings
     *
     * @access  public
     * @return  bool true|false Include Details for Fatal logs from default settings for Fatal logs
     */
    public function getIncludeDetailsInFatalLogsDefault()
    {
        return static::$_defaults['files']['fatal']['details'];
    }
    
    /**
     * set Include Details for Fatal logs for default settings
     *
     * @access  public
     * @param bool true|false $includeDetails Include Details for Fatal logs from default settings for Fatal logs
     * @return Codewiz_Logger instance
     */
    public function setIncludeDetailsInFatalLogsDefault( $includeDetails )
    {
        static::$_defaults['files']['fatal']['details'] = $includeDetails;
        return $this;
    }

}
?>
