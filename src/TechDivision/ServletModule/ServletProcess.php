<?php

/**
 * TechDivision\ServletModule\ServletProcess
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\ServletModule;

/**
 * Is thrown no servlet can be found for a request.
 *
 * @category  Appserver
 * @package   TechDivision_ServletModule
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class ServletProcess extends \Thread
{

    /**
     * The servlet engine instance.
     * 
     * @var \TechDivision\ServletEngine\Engine
     */
    protected $engine;
    
    /**
     * The servlet request instance.
     * 
     * @var \TechDivision\Servlet\Http\HttpServletRequest
     */
    protected $request;
    
    /**
     * The servlet response instance.
     * 
     * @var \TechDivision\Servlet\Http\HttpServletResponse
     */
    protected $response;

    /**
     * Initialize the thread with the necessary instances.
     * 
     * @param \TechDivision\ServletEngine\Engine             $engine   The servlet engine
     * @param \TechDivision\Servlet\Http\HttpServletRequest  $request  The servlet request
     * @param \TechDivision\Servlet\Http\HttpServletResponse $response The servlet response
     * 
     * @return void
     */
    public function __construct($engine, $request, $response)
    {
        $this->engine = $engine;
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Will be invoked when the instance will be shutdown, also 
     * by a fatal error for example.
     * 
     * @return void
     */
    public function shutdown()
    {
        $response = $this->response;
        $response->appendBodyStream(ob_get_clean());
        $this->response = $response;
    }
    
    /**
     * Returns the actual servlet response.
     * 
     * @return \TechDivision\Servlet\Http\HttpServletResponse The servlet response
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * Returns the actual servlet request.
     * 
     * @return \TechDivision\Servlet\Http\HttpServletRequest The servlet request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * The threads main method that processes the request by 
     * calling the engine.
     * 
     * @return void
     */
    public function run()
    {

        // register a shutdown handler
        register_shutdown_function(array(&$this, 'shutdown'));

        // start buffering the output
        ob_start();
        
        // make engine, request + response locally
        $engine = $this->engine;
        $request = $this->request;
        $response = $this->response;

        // register the class loader again + and process the request
        $engine->registerClassLoader();
        $engine->process($request, $response);

        // catch all output and append it to the response body
        $response->appendBodyStream(ob_get_clean());
        
        // ATTENTION: reinitialize request + response (if not, they will only be empty copies)
        $this->request = $request;
        $this->response = $response;
    }
}
