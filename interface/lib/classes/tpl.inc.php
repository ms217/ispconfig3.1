<?php
/**
 * vlibTemplate is a class used to seperate PHP and HTML.
 * For instructions on how to use vlibTemplate, see the
 * vlibTemplate.html file, located in the 'docs' directory.
 *
 * @since 07/03/2002
 * @author Kelvin Jones <kelvin@kelvinjones.co.uk>
 * @package vLIB
 * @access public
 * @see vlibTemplate.html
 */


/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002 Active Fish Group                                 |
// +----------------------------------------------------------------------+
// | Authors: Kelvin Jones <kelvin@kelvinjones.co.uk>                     |
// +----------------------------------------------------------------------+
//
// $Id: class.tpl.inc.php,v 1.1 2003/07/08 12:31:10 platinum Exp $

//** check and avoid multiple loading of class
if (!defined('vlibTemplateClassLoaded')) {

	define('vlibTemplateClassLoaded', 1);
	include_once ISPC_CLASS_PATH.'/tpl_error.inc.php';
	include_once ISPC_CLASS_PATH.'/tpl_ini.inc.php';

	class tpl{

		/*-----------------------------------------------------------------------------\
        |                                 ATTENTION                                    |
        |  Do not touch the following variables. vlibTemplate will not work otherwise. |
        \-----------------------------------------------------------------------------*/
		private $OPTIONS = array(
			'MAX_INCLUDES'          =>   10,
			'TEMPLATE_DIR'          => null,
			'GLOBAL_VARS'           => null,
			'GLOBAL_CONTEXT_VARS'   => null,
			'LOOP_CONTEXT_VARS'     => null,
			'SET_LOOP_VAR'          => null,
			'DEFAULT_ESCAPE'        => null,
			'STRICT'                => null,
			'CASELESS'              => null,
			'UNKNOWNS'              => null,
			'TIME_PARSE'            => null,
			'ENABLE_PHPINCLUDE'     => null,
			'INCLUDE_PATHS'         => array(),
			'CACHE_DIRECTORY'       => null,
			'CACHE_LIFETIME'        => null,
			'CACHE_EXTENSION'       => null
		);

		/** open and close tags used for escaping */
		private $ESCAPE_TAGS = array(
			'html'      => array('open' => 'htmlspecialchars('    , 'close'=> ', ENT_QUOTES)'),
			'url'       => array('open' => 'urlencode('           , 'close'=> ')'),
			'rawurl'    => array('open' => 'rawurlencode('        , 'close'=> ')'),
			'sq'        => array('open' => 'addcslashes('         , 'close'=> ", \"'\")"),
			'dq'        => array('open' => 'addcslashes('         , 'close'=> ", '\"')"),
			'1'         => array('open' => 'htmlspecialchars('    , 'close'=> ', ENT_QUOTES)'),
			'0'         => array('open' => ''                     , 'close'=> ''),
			'none'      => array('open' => ''                     , 'close'=> ''),
			'hex'       => array('open' => '$this->_escape_hex('  , 'close'=> ', false)'),
			'hexentity' => array('open' => '$this->_escape_hex('  , 'close'=> ', true)')
		);



		/** open and close tags used for formatting */
		private $FORMAT_TAGS = array(
			'strtoupper' => array('open' => 'strtoupper(',          'close'=> ')'),
			'uc'         => array('open' => 'strtoupper(',          'close'=> ')'),
			'strtolower' => array('open' => 'strtolower(',          'close'=> ')'),
			'lc'         => array('open' => 'strtolower(',          'close'=> ')'),
			'ucfirst'    => array('open' => 'ucfirst(',             'close'=> ')'),
			'lcucfirst'  => array('open' => 'ucfirst(strtolower(',  'close'=> '))'),
			'ucwords'    => array('open' => 'ucwords(',             'close'=> ')'),
			'lcucwords'  => array('open' => 'ucwords(strtolower(',  'close'=> '))')
		);

		/** operators allowed when using extended TMPL_IF syntax */
		private $allowed_if_ops = array('==', '!=', '<>', '<', '>', '<=', '>=');



		/** dbs allowed by vlibTemplate::setDbLoop(). */
		private $allowed_loop_dbs = array('MYSQL', 'POSTGRESQL', 'INFORMIX', 'INTERBASE', 'INGRES',
			'MSSQL', 'MSQL', 'OCI8', 'ORACLE', 'OVRIMOS', 'SYBASE');



		/** root directory of vlibTemplate automagically filled in */
		private $VLIBTEMPLATE_ROOT = null;



		/** contains current directory used when doing recursive include */
		private $_currentincludedir = array();



		/** current depth of includes */
		private $_includedepth = 0;



		/** full path to tmpl file */
		private $_tmplfilename = null;



		/** file data before it's parsed */
		private $_tmplfile = null;



		/** parsed version of file, ready for eval()ing */
		private $_tmplfilep = null;



		/** eval()ed version ready for printing or whatever */
		private $_tmploutput = null;



		/** array for variables to be kept */
		private $_vars = array();



		/** array where loop variables are kept */
		private $_arrvars = array();

		/** array which holds the current namespace during parse */
		private $_namespace = array();



		/** variable is set to true once the template is parsed, to save re-parsing everything */
		private $_parsed = false;



		/** array holds all unknowns vars */
		private $_unknowns = array();



		/** microtime when template parsing began */
		private $_firstparsetime = null;



		/** total time taken to parse template */
		private $_totalparsetime = null;



		/** name of current loop being passed in */
		private $_currloopname = null;



		/** rows with the above loop */
		private $_currloop = array();



		/** define vars to avoid warnings */
		private $_debug = null;
		private $_cache = null;



		/** array which holds the dynamic Includes */
		private $_dyninclude = array();

		/*-----------------------------------------------------------------------------\
        |                           public functions                                   |
        \-----------------------------------------------------------------------------*/





		/**
		 * Usually called by the class constructor.
		 * Stores the filename in $this->_tmplfilename.
		 * Raises an error if the template file is not found.
		 * @param string $tmplfile full path to template file
		 * @return boolean true
		 * @access public
		 */
		public function newTemplate($tmplfile)
		{
			if (!$tfile = $this->_fileSearch($tmplfile)){
				vlibTemplateError::raiseError('VT_ERROR_NOFILE', KILL, $tmplfile);
			}

			//* make sure that any parsing vars are cleared for the new template
			$this->_tmplfile = null;
			$this->_tmplfilep = null;
			$this->_tmploutput = null;
			$this->_parsed = false;
			$this->_unknowns = array();
			$this->_firstparsetime = null;
			$this->_totalparsetime = null;

			//* reset debug module
			if ($this->_debug){
				$this->_debugReset();
			}
			$this->_tmplfilename = $tfile;
			return true;
		}

		/**
		 * Sets variables to be used by the template
		 * If $k is an array, then it will treat it as an associative array
		 * using the keys as variable names and the values as variable values.
		 * @param mixed $k key to define variable name
		 * @param mixed $v variable to assign to $k
		 * @param bool $encode if set to true use htmlentities on values
		 * @return boolean true/false
		 * @access public
		 */
		public function setVar($k, $v = null, $encode = false)
		{
			global $app;
			
			if (is_array($k)) {
				foreach($k as $key => $value){
					$key = ($this->OPTIONS['CASELESS']) ? strtolower(trim($key)) : trim($key);
					if (preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $key) && $value !== null ) {
						if($encode == true) $value = $app->functions->htmlentities($value);
						$this->_vars[$key] = $value;
					}
				}
			} else {
				if (preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $k) && $v !== null) {
					if ($this->OPTIONS['CASELESS']) $k = strtolower($k);
					if($encode == true) $v = $app->functions->htmlentities($v);
					$this->_vars[trim($k)] = $v;
				} else {
					return false;
				}
			}
			return true;
		}



		/**
		 * Sets dynamic includes to be used by the template
		 * If $k is an array, then it will treat it as an associative array
		 * using the keys as variable names and the values as variable values.
		 * @param mixed $k key to define variable name
		 * @param mixed $v variable to assign to $k
		 * @return boolean true/false
		 * @access public
		 */
		public function setInclude($k, $v = null)
		{
			if(is_array($k)) {
				foreach($k as $key => $val) {
					$this->_dyninclude[$key] = $val;
				}
			} else {
				$this->_dyninclude[$k] = $v;
			}
			return true;
		}

		/**
		 * Unsets a variable which has already been set
		 * Parse in all vars wanted for deletion in seperate parametres
		 * @param string var name to remove use: vlibTemplate::unsetVar(var[, var..])
		 * @return boolean true/false returns true unless called with 0 params
		 * @access public
		 */
		public function unsetVar()
		{
			$num_args = func_num_args();
			if ($num_args < 1)  return false;

			for ($i = 0; $i < $num_args; $i++) {
				$var = func_get_arg($i);
				if ($this->OPTIONS['CASELESS']) $var = strtolower($var);
				if (!preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $var)) continue;
				unset($this->_vars[$var]);
			}
			return true;
		}

		/**
		 * Gets all vars currently set in global namespace.
		 * @return array
		 * @access public
		 */
		public function getVars()
		{
			return empty($this->_vars) ? false : $this->_vars;
		}

		/**
		 * Gets a single var from the global namespace
		 * @return var
		 * @access public
		 */
		public function getVar($var)
		{
			if ($this->OPTIONS['CASELESS']) $var = strtolower($var);
			return (empty($var) || !isset($this->_vars[$var])) ? false : $this->_vars[$var];
		}

		/**
		 * sets the GLOBAL_CONTEXT_VARS
		 * @return true
		 * @access public
		 */
		public function setContextVars()
		{
			$_phpself = @$GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'];
			$_pathinfo = @$GLOBALS['HTTP_SERVER_VARS']['PATH_INFO'];
			$_request_uri = @$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
			$_qs   = @$GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING'];

			//* the following fixes bug of $PHP_SELF on Win32 CGI and IIS.
			$_self = (!empty($_pathinfo)) ? $_pathinfo : $_phpself;
			$_uri  = (!empty($_request_uri)) ? $_request_uri : $_self.'?'.$_qs;

			$this->setvar('__SELF__', $_self);
			$this->setvar('__REQUEST_URI__', $_uri);
			return true;
		}

		/**
		 * Builds the loop construct for use with <TMPL_LOOP>.
		 * @param string $k string to define loop name
		 * @param array $v array to assign to $k
		 * @return boolean true/false
		 * @access public
		 */
		public function setLoop($k, $v)
		{
			if (is_array($v) && preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $k)) {
				$k = ($this->OPTIONS['CASELESS']) ? strtolower(trim($k)) : trim($k);
				$this->_arrvars[$k] = array();
				if ($this->OPTIONS['SET_LOOP_VAR'] && !empty($v)) $this->setvar($k, 1);
				if (($this->_arrvars[$k] = $this->_arrayBuild($v)) == false) {
					vlibTemplateError::raiseError('VT_WARNING_INVALID_ARR', WARNING, $k);
				} else {
					$this->vars['_'.$k.'_num'] = count($v);
				}
			}
			return true;
		}

		/**
		 * [** EXPERIMENTAL **]
		 * Function to create a loop from a Db result resource link.
		 * @param string $loopname to commit loop. If not set, will use last loopname set using newLoop()
		 * @param string $result link to a Db result resource
		 * @param string $db_type, type of db that the result resource belongs to.
		 * @return boolean true/false
		 * @access public
		 */
		public function setDbLoop($loopname, $result, $db_type = 'MYSQL')
		{
			/*
			$db_type = strtoupper($db_type);
            if (!in_array($db_type, $this->allowed_loop_dbs)) {
                vlibTemplateError::raiseError('VT_WARNING_INVALID_LOOP_DB', WARNING, $db_type);
                return false;
            }

            $loop_arr = array();
            // TODO: Are all these necessary as were onyl using mysql and possible postgres ? - pedro
            switch ($db_type) {

                case 'MYSQL':
                    if (get_resource_type($result) != 'mysql result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = mysql_fetch_assoc($result)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'POSTGRESQL':
                    if (get_resource_type($result) != 'pgsql result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }

                    $nr = (function_exists('pg_num_rows')) ? pg_num_rows($result) : pg_numrows($result);

                    for ($i=0; $i < $nr; $i++) {
                        $loop_arr[] = pg_fetch_array($result, $i, PGSQL_ASSOC);
                    }
                    break;

                case 'INFORMIX':
                    if (!$result) {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = ifx_fetch_row($result, 'NEXT')) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'INTERBASE':
                    if (get_resource_type($result) != 'interbase result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = ibase_fetch_row($result)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'INGRES':
                    if (!$result) {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = ingres_fetch_array(INGRES_ASSOC, $result)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'MSSQL':
                    if (get_resource_type($result) != 'mssql result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = mssql_fetch_array($result)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'MSQL':
                    if (get_resource_type($result) != 'msql result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while($r = msql_fetch_array($result, MSQL_ASSOC)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'OCI8':
                    if (get_resource_type($result) != 'oci8 statement') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while(OCIFetchInto($result, &$r, OCI_ASSOC+OCI_RETURN_LOBS)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'ORACLE':
                    if (get_resource_type($result) != 'oracle Cursor') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while(ora_fetch_into($result, &$r, ORA_FETCHINTO_ASSOC)) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'OVRIMOS':
                    if (!$result) {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while(ovrimos_fetch_into($result, &$r, 'NEXT')) {
                        $loop_arr[] = $r;
                    }
                    break;

                case 'SYBASE':
                    if (get_resource_type($result) != 'sybase-db result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }

                    while($r = sybase_fetch_array($result)) {
                        $loop_arr[] = $r;
                    }
                    break;
            }
            $this->setLoop($loopname, $loop_arr);
            return true;
			*/
		}

		/**
		 * Sets the name for the curent loop in the 3 step loop process.
		 * @param string $name string to define loop name
		 * @return boolean true/false
		 * @access public
		 */
		public function newLoop($loopname)
		{
			if (preg_match('/^[a-z_]+[a-z0-9_]*$/i', $loopname)) {
				$this->_currloopname[$loopname] = $loopname;
				$this->_currloop[$loopname] = array();
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Adds a row to the current loop in the 3 step loop process.
		 * @param array $row loop row to add to current loop
		 * @param string $loopname loop to which you want to add row, if not set will use last loop set using newLoop().
		 * @return boolean true/false
		 * @access public
		 */
		public function addRow($row, $loopname = null)
		{
			if (!$loopname) $loopname = $this->_currloopname[(count($this->_currloopname)-1)];

			if (!isset($this->_currloop[$loopname]) || empty($this->_currloopname)) {
				vlibTemplateError::raiseError('VT_WARNING_LOOP_NOT_SET', WARNING);
				return false;
			}
			if (is_array($row)) {
				$this->_currloop[$loopname][] = $row;
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Completes the 3 step loop process. This assigns the rows and resets
		 * the variables used.
		 * @param string $loopname to commit loop. If not set, will use last loopname set using newLoop()
		 * @return boolean true/false
		 * @access public
		 */
		public function addLoop($loopname = null)
		{
			if ($loopname == null) { // add last loop used
				if (!empty($this->_currloop)) {
					foreach ($this->_currloop as $k => $v) {
						$this->setLoop($k, $v);
						unset($this->_currloop[$k]);
					}
					$this->_currloopname = array();
					return true;
				} else {
					return false;
				}
			} elseif (!isset($this->_currloop[$loopname]) || empty($this->_currloopname)) { // newLoop not yet envoked
				vlibTemplateError::raiseError('VT_WARNING_LOOP_NOT_SET', WARNING);
				return false;
			} else { // add a specific loop
				$this->setLoop($loopname, $this->_currloop[$loopname]);
				unset($this->_currloopname[$loopname], $this->_currloop[$loopname]);
			}
			return true;
		}

		/**
		 * Unsets a loop which has already been set.
		 * Can only unset top level loops.
		 * @param string loop to remove use: vlibTemplate::unsetLoop(loop[, loop..])
		 * @return boolean true/false returns true unless called with 0 params
		 * @access public
		 */
		public function unsetLoop()
		{
			$num_args = func_num_args();
			if ($num_args < 1) return false;

			for ($i = 0; $i < $num_args; $i++) {
				$var = func_get_arg($i);
				if ($this->OPTIONS['CASELESS']) $var = strtolower($var);
				if (!preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $var)) continue;
				unset($this->_arrvars[$var]);
			}
			return true;
		}

		/**
		 * Resets the vlibTemplate object. After using vlibTemplate::reset() you must
		 * use vlibTemplate::newTemplate(tmpl) to reuse, not passing in the options array.
		 * @return boolean true
		 * @access public
		 */
		public function reset()
		{
			$this->clearVars();
			$this->clearLoops();
			$this->_tmplfilename = null;
			$this->_tmplfile = null;
			$this->_tmplfilep = null;
			$this->_tmploutput = null;
			$this->_parsed = false;
			$this->_unknowns = array();
			$this->_firstparsetime = null;
			$this->_totalparsetime = null;
			$this->_currloopname = null;
			$this->_currloop = array();
			return true;
		}

		/**
		 * Unsets all variables in the template
		 * @return boolean true
		 * @access public
		 */
		public function clearVars()
		{
			$this->_vars = array();
			return true;
		}

		/**
		 * Unsets all loops in the template
		 * @return boolean true
		 * @access public
		 */
		public function clearLoops()
		{
			$this->_arrvars = array();
			$this->_currloopname = null;
			$this->_currloop = array();
			return true;
		}

		/**
		 * Unsets all variables and loops set using setVar/Loop()
		 * @return boolean true
		 * @access public
		 */
		public function clearAll()
		{
			$this->clearVars();
			$this->clearLoops();
			return true;
		}

		/**
		 * Returns true if unknowns were found after parsing.
		 * Function MUST be called AFTER one of the parsing functions to have any relevance.
		 * @return boolean true/false
		 * @access public
		 */
		public function unknownsExist()
		{
			return !empty($this->_unknowns);
		}

		/**
		 * Alias for unknownsExist.
		 * @access public
		 */
		public function unknowns()
		{
			return $this->unknownsExist();
		}

		/**
		 * Returns an array of all unknown vars found when parsing.
		 * This function is only relevant after parsing a document.
		 * @return array
		 * @access public
		 */
		public function getUnknowns()
		{
			return $this->_unknowns;
		}

		/**
		 * Sets how you want to handle variables that were found in the
		 * template but not set in vlibTemplate using vlibTemplate::setVar().
		 * @param  string $arg ignore, remove, print, leave or comment
		 * @return boolean
		 * @access public
		 */
		public function setUnknowns($arg)
		{
			$arg = strtolower(trim($arg));
			if (preg_match('/^ignore|remove|print|leave|comment$/', $arg)) {
				$this->OPTIONS['UNKNOWNS'] = $arg;
				return true;
			}
			return false;
		}

		/**
		 * function sets the paths to use when including files.
		 * Use of this function: vlibTemplate::setPath(string path [, string path, ..]);
		 * i.e. if $tmpl is your template object do: $tmpl->setPath('/web/htdocs/templates','/web/htdocs/www');
		 * with as many paths as you like.
		 * if this function is called without any arguments, it will just delete any previously set paths.
		 *
		 * @param string path (mulitple)
		 * @return bool success
		 * @access public
		 */
		public function setPath()
		{
			$num_args = func_num_args();
			if ($num_args < 1) {
				$this->OPTIONS['INCLUDE_PATHS'] = array();
				return true;
			}
			for ($i = 0; $i < $num_args; $i++) {
				$thispath = func_get_arg($i);
				array_push($this->OPTIONS['INCLUDE_PATHS'], realpath($thispath));
			}
			return true;
		}

		/**
		 * After using one of the parse functions, this will allow you
		 * access the time taken to parse the template.
		 * see OPTION 'TIME_PARSE'.
		 *
		 * @return float time taken to parse template
		 * @access public
		 */
		public function getParseTime()
		{
			if ($this->OPTIONS['TIME_PARSE'] && $this->_parsed) {
				return $this->_totalparsetime;
			}
			return false;
		}


		/**
		 * Identical to pparse() except that it uses output buffering w/ gz compression thus
		 * printing the output directly and compressed if poss.
		 * Will possibly if parsing a huge template.
		 *
		 * @access public
		 * @return boolean true/false
		 */
		public function fastPrint()
		{
			$ret = $this->_parse('ob_gzhandler');
			print($this->_tmploutput);
			return $ret;
		}


		/**
		 * Calls parse, and then prints out $this->_tmploutput
		 * @access public
		 * @return boolean true/false
		 */
		public function pparse()
		{
			if (!$this->_parsed) $this->_parse();
			print($this->_tmploutput);
			return true;
		}

		/**
		 * Alias for pparse()
		 * @access public
		 */
		public function pprint()
		{
			return $this->pparse();
		}


		/**
		 * Returns the parsed output, ready for printing, passing to mail() ...etc.
		 * Invokes $this->_parse() if template has not yet been parsed.
		 *
		 * @access public
		 * @return boolean true/false
		 */
		public function grab()
		{
			if (!$this->_parsed) $this->_parse();
			return $this->_tmploutput;
		}

		/*-----------------------------------------------------------------------------\
        |                           private functions                                  |
        \-----------------------------------------------------------------------------*/

		/**
		 * vlibTemplate constructor.
		 * if $tmplfile has been passed to it, it will send to $this->newTemplate()
		 * @param string $tmplfile full path to template file
		 * @param array $options see above
		 * @return boolean true/false
		 * @access private
		 */
		public function __construct($tmplfile = null, $options = null)
		{
			if (is_array($tmplfile) && $options == null) {
				$options = $tmplfile;
				unset($tmplfile);
			}

			$this->VLIBTEMPLATE_ROOT = dirname(realpath(__FILE__));

			if (is_array(vlibIni::vlibTemplate())) {
				foreach (vlibIni::vlibTemplate() as $name => $val) {
					$this->OPTIONS[$name] = $val;
				}
			}

			if (is_array($options)) {
				foreach($options as $key => $val) {
					$key = strtoupper($key);
					if ($key == 'PATH') {
						$this->setPath($val);
					} else {
						$this->_setOption($key, strtolower($val));
					}
				}
			}
			if($tmplfile) $this->newTemplate($tmplfile);
			if ($this->OPTIONS['GLOBAL_CONTEXT_VARS']) $this->setContextVars();
			return true;
		}

		/**
		 * function returns the text from the file, or if we're using cache, the text
		 * from the cache file. MUST RETURN DATA.
		 * @param string tmplfile contains path to template file
		 * @param do_eval used for included files. If set then this function must do the eval()'ing.
		 * @access private
		 * @return mixed data/string or boolean
		 */
		private function _getData ($tmplfile, $do_eval=false, $tmpl_from_string = false)
		{
			//* check the current file depth
			if ($this->_includedepth > $this->OPTIONS['MAX_INCLUDES'] || $tmplfile == false) {
				return;
			} else {
				if ($this->_debug){
					if($tmpl_from_string) array_push($this->_debugIncludedfiles, 'String: ' . substr($tmplfile, 0, 25) . '...');
					else array_push($this->_debugIncludedfiles, $tmplfile);
				}
				if ($do_eval) {
					if($tmpl_from_string == true) array_push($this->_currentincludedir, end($this->_currentincludedir));
					else array_push($this->_currentincludedir, dirname($tmplfile));
					$this->_includedepth++;
				}
			}


			if($this->_cache && $this->_checkCache($tmplfile, $tmpl_from_string)) { //* cache exists so lets use it
				$data = file_get_contents($this->_cachefile);
			} else { //* no cache lets parse the file
				if($tmpl_from_string == true) {
					$data = $tmplfile;
				} else {
					$data = file_get_contents($tmplfile);
				}

				$regex = '/(<|<\/|{|{\/|<!--|<!--\/){1}\s*';
				$regex.= 'tmpl_([\w]+)\s*';
				$regex.= '((?:(?:';
				$regex.=    '(?:';
				$regex.=        '(name|format|escape|op|value|file)';
				$regex.=        '\s*=\s*';
				$regex.=    ')?';
				$regex.=    '(?:[\"\'])?';
				$regex.=    '((?<=[\"\'])';
				$regex.=    '[^\"\']*|[a-z0-9_\.]*)';
				$regex.=    '[\"\']?';
				$regex.= ')?\s*)*?)';
				$regex.= '(?:>|\/>|}|-->){1}';
				$regex.= '/i';
				$data = preg_replace_callback($regex, array($this, '_parseTag'), $data);

				if ($this->_cache) { // add cache if need be
					$this->_createCache($data);
				}
			}

			//* now we must parse the $data and check for any <tmpl_include>'s
			if ($this->_debug && $tmpl_from_string == false) $this->doDebugWarnings(file($tmplfile), $tmplfile);

			if ($do_eval) {
				$success = @eval('?>'.$data.'<?php return 1;');
				$this->_includedepth--;
				array_pop($this->_currentincludedir);
				return $success;
			} else {
				return $data;
			}
		}

		/**
		 * Searches for all possible instances of file { $file }
		 * @param string $file path of file we're looking for
		 * @access private
		 * @return mixed fullpath to file or boolean false
		 */
		private function _fileSearch($file)
		{

			$filename = basename($file);
			$filepath = dirname($file);

			if(isset($_SESSION['s']['module']['name']) && isset($_SESSION['s']['theme'])) {
				if(is_file(ISPC_THEMES_PATH.'/'.$_SESSION['s']['theme'].'/templates/'.$_SESSION['s']['module']['name'].'/'.$filename)) {
					return ISPC_THEMES_PATH.'/'.$_SESSION['s']['theme'].'/templates/'.$_SESSION['s']['module']['name'].'/'.$filename;
				}
			}

			//* check fullpath first..
			$fullpath = $filepath.'/'.$filename;
			if (is_file($fullpath)) return $fullpath;

			//* ..then check for relative path for current directory..
			if (!empty($this->_currentincludedir)) {
				$currdir = $this->_currentincludedir[(count($this->_currentincludedir) -1)];
				$relativepath = realpath($currdir.'/'.$filepath.'/'.$filename);
				if (is_file($relativepath)) {
					array_push($this->_currentincludedir, dirname($relativepath));
					return $relativepath;
				}
			}

			//* ..then check for relative path for all additional given paths..
			if (!empty($this->OPTIONS['INCLUDE_PATHS'])) {
				foreach ($this->OPTIONS['INCLUDE_PATHS'] as $currdir) {
					$relativepath = realpath($currdir.'/'.$filepath.'/'.$filename);
					if (is_file($relativepath)) {
						return $relativepath;
					}
				}
			}

			//* ..then check path from TEMPLATE_DIR..
			if (!empty($this->OPTIONS['TEMPLATE_DIR'])) {
				$fullpath = realpath($this->OPTIONS['TEMPLATE_DIR'].'/'.$filepath.'/'.$filename);
				if (is_file($fullpath)) return $fullpath;
			}

			//* ..then check relative path from executing php script..
			$fullpath = realpath($filepath.'/'.$filename);
			if (is_file($fullpath)) return $fullpath;

			//* ..then check path from template file.
			if (!empty($this->VLIBTEMPLATE_ROOT)) {
				$fullpath = realpath($this->VLIBTEMPLATE_ROOT.'/'.$filepath.'/'.$filename);
				if (is_file($fullpath)) return $fullpath;
			}

			return false; // uh oh, file not found
		}

		/**
		 * Modifies the array $arr to add Template variables, __FIRST__, __LAST__ ..etc
		 * if $this->OPTIONS['LOOP_CONTEXT_VARS'] is true.
		 * Used by $this->setloop().
		 * @param array $arr
		 * @return array new look array
		 * @access private
		 */
		private function _arrayBuild($arr)
		{
			if (is_array($arr) && !empty($arr)) {
				$arr = array_values($arr); // to prevent problems w/ non sequential arrays
				for ($i = 0; $i < count($arr); $i++) {
					if(!is_array($arr[$i]))  return false;
					foreach ($arr[$i] as $k => $v) {
						unset($arr[$i][$k]);
						if ($this->OPTIONS['CASELESS']) $k = strtolower($k);
						if (preg_match('/^[0-9]+$/', $k)) $k = '_'.$k;

						if (is_array($v)) {
							if (($arr[$i][$k] = $this->_arrayBuild($v)) == false) return false;
						} else { // reinsert the var
							$arr[$i][$k] = $v;
						}
					}
					if ($this->OPTIONS['LOOP_CONTEXT_VARS']) {
						if ($i == 0) $arr[$i]['__FIRST__'] = true;
						if (($i + 1) == count($arr)) $arr[$i]['__LAST__'] = true;
						if ($i != 0 && (($i + 1) < count($arr))) $arr[$i]['__INNER__'] = true;
						if (is_int(($i+1) / 2))  $arr[$i]['__EVEN__'] = true;
						if (!is_int(($i+1) / 2))  $arr[$i]['__ODD__'] = true;
						$arr[$i]['__ROWNUM__'] = ($i + 1);
					}
				}
				return $arr;
			} elseif (empty($arr)) {
				return true;
			}
		}

		/**
		 * returns a string used for parsing in tmpl_if statements.
		 * @param string $varname
		 * @param string $value
		 * @param string $op
		 * @param string $namespace current namespace
		 * @access private
		 * @return string used for eval'ing
		 */
		private function _parseIf($varname, $value = null, $op = null, $namespace = null, $format = null)
		{
			if (isset($namespace)) $namespace = substr($namespace, 0, -1);
			$comp_str = ''; // used for extended if statements

			// work out what to put on the end id value="whatever" is used
			if (isset($value)) {

				// add the correct operator depending on whether it's been specified or not
				if (!empty($op)) {
					if (in_array($op, $this->allowed_if_ops)) {
						$comp_str .= $op;
					} else {
						vlibTemplateError::raiseError('VT_WARNING_INVALID_IF_OP', WARNING, $op);
					}
				} else {
					$comp_str .= '==';
				}

				// now we add the value, if it's numeric, then we leave the quotes off
				if (is_numeric($value)) {
					$comp_str .= $value;
				} else {
					$comp_str .= '\''.$value.'\'';
				}
			}

			if (count($this->_namespace) == 0 || $namespace == 'global') return '$this->_vars[\''.$varname.'\']'.$comp_str;
			$retstr = '$this->_arrvars';
			$numnamespaces = count($this->_namespace);
			for ($i=0; $i < $numnamespaces; $i++) {
				if ($this->_namespace[$i] == $namespace || (($i + 1) == $numnamespaces && !empty($namespace))) {
					$retstr .= "['".$namespace."'][\$_".$i."]";
					break 1;
				} else {
					$retstr .= "['".$this->_namespace[$i]."'][\$_".$i."]";
				}
			}
			if ($this->OPTIONS['GLOBAL_VARS'] && empty($namespace)) {
				$retstr = '(('.$retstr.'[\''.$varname.'\'] !== null) ? '.$retstr.'[\''.$varname.'\'] : $this->_vars[\''.$varname.'\'])';
				if(isset($format) && isset($value) && $format == 'version') {
					return 'version_compare(' . $retstr . ', \'' . $value . '\', \'' . (!empty($op) ? $op : '==') . '\')';
				} else {
					return $retstr.$comp_str;
				}
			}
			else {
				if(isset($format) && isset($value) && $format == 'version') {
					return 'version_compare(' . $retstr."['".$varname."']" . ', \'' . $value . '\', \'' . (!empty($op) ? $op : '==') . '\')';
				} else {
					return $retstr."['".$varname."']".$comp_str;
				}
			}
		}

		/**
		 * returns a string containing hook data
		 * @param string $type
		 * @param string $name
		 * @return string hook data
		 */
		private function _parseHook ($name)
		{
			global $app;
			
			if(!$name) return false;
			
			$module = isset($_SESSION['s']['module']['name']) ? $_SESSION['s']['module']['name'] : '';
			$form = isset($app->tform->formDef['name']) ? $app->tform->formDef['name'] : '';
			
			$events = array();
			if($module) {
				$events[] = $module . ':' . ($form ? $form : '') . ':' . $name;
				$events[] = $module . ':' . ($form ? $form : '') . ':on_template_content';
			} else {
				$events[] = $name;
				$events[] = 'on_template_content';
			}
			
			$events = array_unique($events);
			
			for($e = 0; $e < count($events); $e++) {
				$tmpresult = $app->plugin->raiseEvent($events[$e], array(
					'name' => $name,
					'module' => $module,
					'form' => $form
				), true);
				if(!$tmpresult) $tmpresult = '';
				else $tmpresult = $this->_getData($tmpresult, false, true);
				
				$result .= $tmpresult;
			}
			
			return $result;
		}

		/**
		 * returns a string used for parsing in tmpl_loop statements.
		 * @param string $varname
		 * @access private
		 * @return string used for eval'ing
		 */
		private function _parseLoop ($varname)
		{
			array_push($this->_namespace, $varname);
			$tempvar = count($this->_namespace) - 1;
			$retstr = "for (\$_".$tempvar."=0 ; \$_".$tempvar." < count(\$this->_arrvars";
			for ($i=0; $i < count($this->_namespace); $i++) {
				$retstr .= "['".$this->_namespace[$i]."']";
				if ($this->_namespace[$i] != $varname) $retstr .= "[\$_".$i."]";
			}
			return $retstr."); \$_".$tempvar."++) {";
		}

		/**
		 * returns a string used for parsing in tmpl_var statements.
		 * @param string $wholetag
		 * @param string $tag
		 * @param string $varname
		 * @param string $escape
		 * @param string $format
		 * @param string $namespace
		 * @access private
		 * @return string used for eval'ing
		 */
		private function _parseVar ($wholetag, $tag, $varname, $escape, $format, $namespace)
		{
			if (!empty($namespace)) $namespace = substr($namespace, 0, -1);
			$wholetag = stripslashes($wholetag);

			if (count($this->_namespace) == 0 || $namespace == 'global') {
				$var1 = '$this->_vars[\''.$varname.'\']';
			} else {
				$var1build = "\$this->_arrvars";
				$numnamespaces = count($this->_namespace);
				for ($i=0; $i < $numnamespaces; $i++) {
					if ($this->_namespace[$i] == $namespace || (($i + 1) == $numnamespaces && !empty($namespace))) {
						$var1build .= "['".$namespace."'][\$_".$i."]";
						break 1;
					} else {
						$var1build .= "['".$this->_namespace[$i]."'][\$_".$i."]";
					}
				}
				$var1 = $var1build . "['$varname']";
				if ($this->OPTIONS['GLOBAL_VARS'] && empty($namespace)) {
					$var2 = '$this->_vars[\''.$varname.'\']';
				}
			}

			$beforevar = '';
			$aftervar  = '';
			if (!empty($escape)&& isset($this->ESCAPE_TAGS[$escape])) {
				$beforevar .= $this->ESCAPE_TAGS[$escape]['open'];
				$aftervar   = $this->ESCAPE_TAGS[$escape]['close'] . $aftervar;
			}

			if (!empty($format)&& isset($this->FORMAT_TAGS[$format])) {
				$beforevar .= $this->FORMAT_TAGS[$format]['open'];
				$aftervar   = $this->FORMAT_TAGS[$format]['close'] . $aftervar;
			}

			//* build return values
			$retstr  = 'if ('.$var1.' !== null) { ';
			$retstr .= 'print('.$beforevar.$var1.$aftervar.'); ';
			$retstr .= '}';

			if (@$var2) {
				$retstr .= ' elseif ('.$var2.' !== null) { ';
				$retstr .= 'print('.$beforevar.$var2.$aftervar.'); ';
				$retstr .= '}';
			}

			switch (strtolower($this->OPTIONS['UNKNOWNS'])) {
			case 'comment':
				$comment = addcslashes('<!-- unknown variable '.preg_replace('/<!--|-->/', '', $wholetag).'//-->', '"');
				$retstr .= ' else { print("'.$comment.'"); $this->_setUnknown("'.$varname.'"); }';
				return $retstr;

			case 'leave':
				$retstr .= ' else { print("'.addcslashes($wholetag, '"').'"); $this->_setUnknown("'.$varname.'"); }';
				return $retstr;

			case 'print':
				$retstr .= ' else { print("'.htmlspecialchars($wholetag, ENT_QUOTES).'"); $this->_setUnknown("'.$varname.'"); }';
				return $retstr;

			case 'ignore':
				return $retstr;

			case 'remove':
			default:
				$retstr .= ' else { $this->_setUnknown("'.$varname.'"); }';
				return $retstr;

			}
		}

		/**
		 * takes values from preg_replace in $this->_intparse() and determines
		 * the replace string.
		 *
		 * @param array $args array of all matches found by preg_replace
		 * @access private
		 * @return string replace values
		 */
		private function _parseTag ($args)
		{
			$wholetag = $args[0];
			$openclose = $args[1];
			$tag = strtolower($args[2]);
			
			if ($tag == 'else') return '<?php } else { ?>';
			if ($tag == 'tmpl_include') return $wholetag; // ignore tmpl_include tags

			if (preg_match("/^<\/|{\/|<!--\/$/s", $openclose) || preg_match("/^end[if|loop|unless|comment]$/", $tag)) {
				if ($tag == 'loop' || $tag == 'endloop') array_pop($this->_namespace);
				if ($tag == 'comment' || $tag == 'endcomment') {
					return '<?php */ ?>';
				} else {
					return '<?php } ?>';
				}
			}

			// arrange attributes
			$tmp_atts = $args[3];
			$atts = preg_split('/\s+/', $tmp_atts);
			foreach($atts as $att) {
				$regex =    '/(?:';
				$regex.=        '(name|format|escape|op|value|file)';
				$regex.=        '\s*=\s*';
				$regex.=    ')?';
				$regex.=    '(?:[\"\'])?';
				$regex.=    '((?<=[\"\'])';
				$regex.=    '[^\"\']*|[a-z0-9_\.]*)';
				$regex.=    '[\"\']?/';
				if(preg_match($regex, $att, $match)) {
					$key = (empty($match[1])) ? 'name' : strtolower($match[1]);
					if ($key == 'name' && preg_match('/^(php)?include$/', $tag)) $key = 'file';
					$$key = $match[2];
				}
			}

			$var = ($this->OPTIONS['CASELESS']) ? strtolower($name) : $name;

			if ($this->_debug && !empty($var)) {
				if (preg_match("/^global\.([A-Za-z_]+[_A-Za-z0-9]*)$/", $var, $matches)) $var2 = $matches[1];
				if (empty($this->_debugTemplatevars[$tag])) $this->_debugTemplatevars[$tag] = array();
				if (!isset($var2)) $var2 = $var;
				if (!in_array($var2, $this->_debugTemplatevars[$tag])) array_push($this->_debugTemplatevars[$tag], $var2);
			}

			if (preg_match("/^([A-Za-z_]+[_A-Za-z0-9]*(\.)+)?([A-Za-z_]+[_A-Za-z0-9]*)$/", $var, $matches)) {
				$var = $matches[3];
				$namespace = $matches[1];
			}


			//* return correct string (tag dependent)
			switch ($tag) {
			case 'var':
				if (empty($escape) && (!empty($this->OPTIONS['DEFAULT_ESCAPE']) && strtolower($this->OPTIONS['DEFAULT_ESCAPE']) != 'none')) {
					$escape = strtolower($this->OPTIONS['DEFAULT_ESCAPE']);
				}
				return '<?php '.$this->_parseVar ($wholetag, $tag, $var, @$escape, @$format, @$namespace)." ?>\n";

			case 'if':
				return '<?php if ('. $this->_parseIf($var, @$value, @$op, @$namespace, @$format) .') { ?>';

			case 'unless':
				return '<?php if (!'. $this->_parseIf($var, @$value, @$op, @$namespace, @$format) .') { ?>';

			case 'elseif':
				return '<?php } elseif ('. $this->_parseIf($var, @$value, @$op, @$namespace, @$format) .') { ?>';

			case 'loop':
				return '<?php '. $this->_parseLoop($var) .'?>';

			case 'comment':
				if (empty($var)) { // full open/close style comment
					return '<?php /* ?>';
				} else { // just ignore tag if it was a one line comment
					return;
				}

			case 'phpinclude':
				if ($this->OPTIONS['ENABLE_PHPINCLUDE']) {
					return '<?php include(\''.$file.'\'); ?>';
				}
			
			case 'hook':
				return $this->_parseHook(@$var);
			
			case 'include':
				return '<?php $this->_getData($this->_fileSearch(\''.$file.'\'), 1); ?>';

			case 'dyninclude':
				return '<?php $this->_getData($this->_fileSearch($this->_dyninclude[\''.$name.'\']), 1); ?>';

			default:
				if ($this->OPTIONS['STRICT']) vlibTemplateError::raiseError('VT_ERROR_INVALID_TAG', KILL, htmlspecialchars($wholetag, ENT_QUOTES));
				break;
			}

		}

		/**
		 * Parses $this->_tmplfile into correct format for eval() to work
		 * Called by $this->_parse(), or $this->fastPrint, this replaces all <tmpl_*> references
		 * with their correct php representation, i.e. <tmpl_var title> becomes $this->vars['title']
		 * Sets final parsed file to $this->_tmplfilep.
		 *
		 * @access private
		 * @return boolean true/false
		 */
		private function _intParse ()
		{
			//$mqrt = get_magic_quotes_runtime();
			//set_magic_quotes_runtime(0);
			$this->_tmplfilep = '?>'.$this->_getData($this->_tmplfilename).'<?php return true;';
			//set_magic_quotes_runtime($mqrt);
			return true;
		}

		/**
		 * Calls _intParse, and eval()s $this->tmplfilep
		 * and outputs the results to $this->tmploutput
		 *
		 * @param bool compress whether to compress contents
		 * @access private
		 * @return boolean true/false
		 */
		private function _parse ($compress = '')
		{
			if (!$this->_parsed) {
				if ($this->OPTIONS['TIME_PARSE']) $this->_firstparsetime = $this->_getMicroTime();

				$this->_intParse();
				$this->_parsed = true;

				if ($this->OPTIONS['TIME_PARSE']) $this->_totalparsetime = ($this->_getMicroTime() - $this->_firstparsetime);
				if ($this->OPTIONS['TIME_PARSE'] && $this->OPTIONS['GLOBAL_CONTEXT_VARS']) $this->setVar('__PARSE_TIME__', $this->getParseTime());
			}

			// ob_start($compress);
			ob_start();

			array_push($this->_currentincludedir, dirname($this->_tmplfilename));
			$this->_includedepth++;
			$success = @eval($this->_tmplfilep);
			$this->_includedepth--;
			array_pop($this->_currentincludedir);

			if ($this->_debug) $this->doDebug();
			if (!$success) vlibTemplateError::raiseError('VT_ERROR_PARSE', FATAL);
			$this->_tmploutput .= ob_get_contents();
			ob_end_clean();
			return true;
		}

		/**
		 * Sets one or more of the boolean options 1/0, that control certain actions in the template.
		 * Use of this function:
		 * either: vlibTemplate::_setOptions(string option_name, bool option_val [, string option_name, bool option_val ..]);
		 * or      vlibTemplate::_setOptions(array);
		 *          with an associative array where the key is the option_name
		 *          and the value is the option_value.
		 *
		 * @param mixed (mulitple)
		 * @return bool true/false
		 * @access private
		 */
		private function _setOption()
		{
			$numargs = func_num_args();
			if ($numargs < 1) {
				vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
				return false;
			}

			if ($numargs == 1) {
				$options = func_get_arg(1);
				if (is_array($options)) {
					foreach ($options as $k => $v) {
						if ($v != null) {
							if(in_array($k, array_keys($this->OPTIONS))) $this->OPTIONS[$k] = $v;
						} else {
							continue;
						}
					}
				} else {
					vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
					return false;
				}
			}elseif (is_int($numargs / 2)) {
				for ($i = 0; $i < $numargs; $i=($i+2)) {
					$k  = func_get_arg($i);
					$v = func_get_arg(($i+1));
					if ($v != null) {
						if(in_array($k, array_keys($this->OPTIONS))) $this->OPTIONS[$k] = $v;
					}
				}
			} else {
				vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
				return false;
			}
			return true;
		}

		/**
		 * Used during parsing, this function sets an unknown var checking to see if it
		 * has been previously set.
		 * @param string var
		 * @access private
		 */
		private function _setUnknown($var)
		{
			if (!in_array($var, $this->_unknowns)) array_push($this->_unknowns, $var);
		}

		/**
		 * Returns microtime as a float number
		 * @return float microtime
		 * @access private
		 */
		private function _getMicrotime()
		{
			list($msec, $sec) = explode(' ', microtime());
			return (float)$msec + (float)$sec;
		}

		/**
		 * Returns str encoded to hex code.
		 * @param string str to be encoded
		 * @param bool true/false specify whether to use hex_entity
		 * @return string encoded in hex
		 * @access private
		 */
		private  function _escape_hex($str = '', $entity = false) {
			$prestr = $entity ? '&#x' : '%';
			$poststr= $entity ? ';' : '';
			for ($i=0; $i < strlen($str); $i++) {
				$return .= $prestr.bin2hex($str[$i]).$poststr;
			}
			return $return;
		}

		/*- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    The following functions have no use and are included just so that if the user
    is making use of vlibTemplateCache functions, this doesn't crash when changed to
    vlibTemplate if the user is quickly bypassing the vlibTemplateCache class.
    - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -*/
		function clearCache()        {vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'clearCache()');}

		function recache()           {vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'recache()');}

		function setCacheLifeTime()  {vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'setCacheLifeTime()');}

		function setCacheExtension() {vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'setCacheExtension()');}

	} // << end class Def

	//include_once (ISPC_CLASS_PATH.'/vlibTemplate/debug.php');
	include_once ISPC_CLASS_PATH.'/tpl_cache.inc.php';

} // << end if(!defined())..
?>
