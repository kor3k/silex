<?php

namespace Core;

use Silex\Application as SilexApplication;
use Silex\Route\SecurityTrait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Monolog\Logger;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;

class Application extends SilexApplication
{
    use SecurityTrait;
    use SilexApplication\TwigTrait;
    use SilexApplication\SecurityTrait;    
    use SilexApplication\FormTrait;
    use SilexApplication\UrlGeneratorTrait;
    use SilexApplication\SwiftmailerTrait
    {
	mail as protected _mail;
    }
    use SilexApplication\MonologTrait;
    use SilexApplication\TranslationTrait;
    
/**
 * 
 * @param array $values
 */    
    public function __construct(array $values = array())    
    {
	parent::__construct( $values );
	
	if( !$this['debug'] )
	{
	    ErrorHandler::register();
	    $this->error(
	    function( \Exception $e , $code )
	    {
		return $this->handleError( $e , $code );
	    });	    
	}
	
	Request::enableHttpMethodParameterOverride();
    }
/**
 * @inheritdoc
 */    
    public function redirect( $url , $status = 302 )
    {
        $response   =	parent::redirect( $url , $status );
	$this->terminate( $this['request'] , $response );
	return $response;
    }    
    
/**
 * 
 * @param \Swift_Message $message
 */    
    public function mail( \Swift_Message $message )
    {    	
	if( $this['debug'] )
	{
	    $message->setTo( $this['mailer_user'] );	    
	}	
	
	return $this->_mail( $message );
	
//	else
//	{	    	    
//	    $this->finish
//	    (function() use( $message )
//	    {		
//		$this->_mail( $message );	
//	    });	    
//	    
//	    return null;
//	}
    }    
    
/**
 * 
 * @param \Symfony\Component\HttpFoundation\Request $request
 * @return string
 */    
    protected function logRequest( Request $request )
    {
	$message    =	'request';
	$context    =	(array)$request;
	
	$this->log( $message , $context );
	
	return $message;
    }
    
/**
 * 
 * @param \Exception $e
 * @param int $code
 * @return Response
 */    
    protected function handleError( \Exception $e , $code )
    {
	if( $e instanceof NotFoundHttpException )
	{
	    $response	=   $this->render( '404.html.twig' , [ 'error' => $e ] );
	}
	else
	{
	    $response	=   $this->render( 'error.html.twig' , [ 'error' => $e ] );
	}
	
	$this->prepareResponse( $response );
	
	return $response;
    }
    
/**
 * 
 * @param \Symfony\Component\HttpFoundation\Response $response
 * @return \Symfony\Component\HttpFoundation\Response
 */    
    protected function prepareResponse( Response $response )
    {
	$response->setTtl( $this['response_ttl'] );	    
	$response->setProtocolVersion( '1.1' );
	$response->headers->set( 'Content-Language' , $this['locale'] );	
	$response->setCharset( $this['charset'] );
	
	return $response;
    }
    
    protected function initNativeSession()
    {
	$this->register(new Silex\Provider\SessionServiceProvider(), array(
	   'session.storage.options'	    =>	array(	       
		'cookie_lifetime'   => 3600 ,     
	   ),	
	));	        
    }
    
/**
 * 
 * @param \Doctrine\DBAL\Connection $connection
 */    
    protected function initDbSession( Connection $connection )
    {
	$this->register(new Silex\Provider\SessionServiceProvider(), array(
	   'session.storage.options'	    =>	array(	       
		'cookie_lifetime'   => 3600 ,     
	   ),	
	));		
	
	$this['session.storage.handler'] = $this->share(
	function() use( $connection ) 
	{
	    return new PdoSessionHandler(
		$connection->getWrappedConnection(),
		[
		    'db_table'      => 'session',
		    'db_id_col'     => 'id_session',
		    'db_data_col'   => 'value',
		    'db_time_col'   => 'timestamp',
		],
		$this['session.storage.options']
	    );
	});	
    }
    
/**
 * 
 * @param string $host
 * @param string $dbname
 * @param string $user
 * @param string $password
 */    
    protected function initDoctrine( $host , $dbname , $user , $password )
    {
	$this->register(new \Silex\Provider\DoctrineServiceProvider(), array(
	    'dbs.options' => array (
		$dbname => array(
		    'driver'    => 'pdo_mysql',
		    'host'      =>  $host,
		    'dbname'    =>  $dbname,
		    'user'      =>  $user,
		    'password'  =>  $password,
		    'charset'   =>  'UTF8',
		),
	    ),
	));	
    }
    
    protected function initDoctrineOrm()
    {
        $this->register(new DoctrineOrmServiceProvider, array(
            "orm.proxies_dir" => DIR ."/cache/proxies",
            "orm.em.options" => array(
                "mappings" => array(
                    array(
                        "type" => "yml",
                        "namespace" => "App\Entity",
                        "path" => DIR."/src/Resources/config/doctrine",
                    ),
                ),
            ),
        ));
    }
    
/**
 * 
 * @param integer $ttl
 */    
    protected function initHttpCache( $ttl = 3600 )
    {
	$this->register(new \Silex\Provider\HttpCacheServiceProvider(), array(
	   'http_cache.cache_dir'   =>	DIR.'/cache/',
	   'http_cache.esi'	    =>	null,
	   'http_cache.options'	    =>	array(	       
		'debug'                  => false,
		'default_ttl'            => (int)$ttl,
		'private_headers'        => array('Authorization', 'Cookie'),
		'allow_reload'           => true,
		'allow_revalidate'       => true,
		'stale_while_revalidate' => 2,
		'stale_if_error'         => 60,	       
	   ),	
	));			
    }
    
/**
 * 
 * @param string $user
 * @param string $password
 * @param string $sender
 */    
    protected function initSwiftmailer( $user , $password , $sender = null )
    {			
	$this->register(new \Silex\Provider\SwiftmailerServiceProvider(), array(
            'swiftmailer.options'     =>        array(			
                'transport'         =>  'gmail',
                'host'              =>  'smtp.gmail.com',
                'username'          =>  $user,
                'password'          =>  $password ,
                'sender_address'    =>  $sender ?: $user, 
                'encryption'        =>  'tls' ,
                'auth_mode'         =>  'login' ,
                'port'              =>  587 ,
                    )
	));
	
    }
    
    protected function initUrlGenerator()
    {
	$this->register(new \Silex\Provider\UrlGeneratorServiceProvider());
    }
    
    protected function initForm()
    {        
        $this->register(new \Silex\Provider\FormServiceProvider());
        
        /* forms needs translator for error messages etc */
        
	if( !isset( $this['translator'] ) )
        {
            $this->register(new \Silex\Provider\TranslationServiceProvider(), array(
            ));            
        }        
    }
    
/**
 * 
 * @param array $security security config
 */    
    protected function initSecurity( array $security = array() )
    {		
	$this->register(new \Silex\Provider\SecurityServiceProvider(), $security );	
    }
    
/**
 * 
 * @param string $pattern
 * @param array $users
 */
    protected function initBasicHttpSecurity( $pattern , array $users = array( 'admin' => [ 'ROLE_ADMIN' , 'vstup' ] ) )
    {		
	$this['security.role_hierarchy'] = array(
	    'ROLE_ADMIN' => [ 'ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH' ],
	);	 		
	
	$this['security.firewalls']   =	
	[
	    'admin' =>	
	    [
		'pattern'   =>  $pattern,
		'http'	    =>  true ,		    
		'stateless' =>  true ,
		'users'	    =>	$users
	    ]
	];	
	
	$this->initSecurity();
	
	$this['security.encoder.digest'] = $this->share(
	function( $app ) 
	{
	    return new PlaintextPasswordEncoder();
	});	
    }
      
    protected function initTwig()
    {
	$this->register(new \Silex\Provider\TwigServiceProvider(), array(
	    'twig.path' => DIR.'/src/Resources/views',
	));		
    }
    
    protected function initMonolog()
    {
	$this->register(new \Silex\Provider\MonologServiceProvider(), array(
	    'monolog.logfile'	=>  DIR.'/log/'. ( $this['debug'] ? 'dev' : 'prod' ) .'.log',
	    'monolog.level'	=>  $this['debug'] ? Logger::DEBUG : Logger::INFO ,
	    'monolog.name'	=>  'app' ,
	));	
    }
    
    protected function initValidator()
    {
	$this->register(new \Silex\Provider\ValidatorServiceProvider());
    }
    
    protected function initTranslation( $locale , $localeFallback = null )
    {
	$this->register(new \Silex\Provider\TranslationServiceProvider(), array(
	    'locale'		=>  $locale ,
	    'locale_fallback'	=>  $localeFallback ?: $locale ,
	));	
	
	$this['translator.domains'] = array(
	    'messages' => array(
		'en' => array(
		    'hello'     => 'Hello %name%',
		    'goodbye'   => 'Goodbye %name%',
		),
		'de' => array(
		    'hello'     => 'Hallo %name%',
		    'goodbye'   => 'Tschüss %name%',
		),
		'fr' => array(
		    'hello'     => 'Bonjour %name%',
		    'goodbye'   => 'Au revoir %name%',
		),
	    ),
	    'validators' => array(
		'fr' => array(
		    'This value should be a valid number.' => 'Cette valeur doit être un nombre.',
		),
	    ),
	);	
    }
}