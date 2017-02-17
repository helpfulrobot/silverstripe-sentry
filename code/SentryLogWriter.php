<?php

namespace \Sentry\Sentry;

require_once THIRDPARTY_PATH . '/Zend/Log/Writer/Abstract.php';

/**
 * The SentryLogWriter class simply acts as a bridge between the configured Sentry 
 * adaptor and SilverStripe's {@link SS_Log}.
 * 
 * Usage in your project's _config.php for example:
 *  
 *    SS_Log::add_writer(SentryLogWriter::factory(), '<=');
 * 
 * @author Russell Michell 2017 <https://theruss.com/>
 * @package sentry
 * @todo Ensure the following are reported:
 *  - The URL at which the error occurred
 *  - [DONE] The log-level taken from SS_Log
 *  - [DONE] Any available logged-in member data
 *  - [DONE] The environment
 *  - Arbitrary additional "tags" as key=>value pairs, passed at call time
 */

class SentryLogWriter extends Zend_Log_Writer_Abstract
{
    /**
     * The constructor is usually called from factory().
     * 
     * @param string $env   Optionally pass a different environment.
     * @param array $tags   Additional key=>value pairs we may wish to report in
     *                      addition to that which is available by default in the
     *                      module and in Sentry itself.
     */
    public function __construct($env = null, array $tags = [])
    {
        // Set default environment
        if (is_null($env)) {
            $env = Director::get_environment_type();  
        }
        
        $this->client->setEnv($env);
        
        // Set all available user-data
        if ($member = Member::currentUser()) {
            $this->client->setUserData($member->toMap());
        }
        
        // Set any available tags available in SS config
        if ($tags) {
            $this->client->setTags(array_merge(
                $this->tags(),
                $tags
            ));
        }
    }
    
    /**
     * For flexibility, the factory should be the usual entry point into this class,
     * but there's no reason the constructor can't be called directly if for example, only
     * local errror-reporting is required.
     * 
     * @param array $config
     * @return SentryLogWriter
     */
	public static function factory($config)
    {
        $env = isset($config['env']) ? $config['env'] : null;
        $tags = isset($config['tags']) ? $config['tags'] : null;
        
		return Injector::inst()->create('SentryLogWriter', $env, $tags);
	}
    
    /**
     * Sets-up a default set of additional tags we wish to send to Sentry.
     * By default, Sentry reports on several mertrics, and we're already sending 
     * {@link Member} data. But there are additional data that would be useful
     * for debugging via the Sentry UI:
     * 
     * - framework version
     * 
     * @return array
     * @todo
     */
    public function tags()
    {
        return [
            'composer-info' => $this->composerInfo()
        ];
    }
    
    /**
     * @param array $event  An array of data that is create din, and arrives here
     *                      via {@link SS_Log::log()}. 
     * @return void
     */
    protected function _write($event)
    {
        $message = $event['message']['errstr'];             // From SS_Log::log()
        $data = [
            'level'     => $this->client->level($event['priorityName']),
            'timestamp' => strtotime($event['timestamp']),  // From ???
            'file'      => $event['message']['errfile'],    // From SS_Log::log()
            'line'      => $event['message']['errline'],    // From SS_Log::log()
            'context'   => $event['message']['errcontext'], // From SS_Log::log()
            'tags'      => $this->client->getTags()
        ];
        $trace = SS_Backtrace::filter_backtrace(debug_backtrace(), ['SentryLogWriter->_write']);
        
        $this->client->send($message, [], $data, $trace);
    }
    
    /**
     * Return a formatted result of running the "composer info" command.
     * 
     * @return array
     */
    protected function composerInfo()
    {
        $return = 0;
        $result = passthru('cd ' . APP_DIR . ' && composer info', $return);
        
        if ($return === 0) {
            return var_export($result, true);
        }
        
        return 'Unavailable';
    }
    
}
