<?php

namespace Core;

use Silex\ControllerCollection;

abstract class AbstractController
{
/**
 *
 * @var Application
 */    
    protected	$app;
    
    public function __construct( Application $app )
    {
	    $this->app  =	$app;
    }
    
/**
 * 
 * @param \Silex\ControllerCollection $controllers
 * @return \Silex\ControllerCollection
 */    
    public function __invoke( ControllerCollection $controllers = null )
    {
	    return $this->connect( $controllers ?: $this->app['controllers_factory'] );
    }

    /**
     * @param \Silex\ControllerCollection $controllers
     * @return \Silex\ControllerCollection
     */
    abstract protected function connect( ControllerCollection $controllers );

}