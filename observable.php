<?php
/**
 * @author      Adrian Testa-Avila <at@atapp.info>
 * @copyright   2012-2014
 * @license     MIT
 */

trait observable{
    
##  SplObserver METHODS  ##

    /**
     * @see http://php.net/splobserver.update
     *
     * @param string $1                 event this update is in response to
     */
    public function update( \SplSubject $subject ){
        if( ! ($this instanceof \SplObserver) ){ return; }
        $event = func_get_arg( 1 )?: "*";
        try{
            $updateMethod = $this->conf( "updates.$event" )?:
                [$this,"_update_$event"];
            if( is_callable( $updateMethod ) ){
                $updateMethod( $subject,$event );
            }
        }
        catch( \Exception $e ){
            $o = get_called_class();
            $s = get_class( $subject );
            $m = "update failed on event(observer:subject) [$event($o:$s)]: {$e->getMessage()}";
            throw new \BadFunctionCallException( $m,E_USER_ERROR );
        }
    }
    
##  SplSubject METHODS  ##
    
    /**
     * @see http://php.net/splsubject.attach
     *
     * @param string $1                 name of event to attach to observer
     */
    public function attach( \SplObserver $observer ){
        static $_observers;
        if( ! ($this instanceof \SplSubject) ){ return; }
        if( ! $_observers ){ $_observers = $this->_observers(); }
        $event = func_get_arg( 1 )?: "*";
        // existing observer
        if( $_observers->offsetExists( $observer ) ){
            $_observers->offsetGet( $observer )->offsetSet( $event );
        }
        // new observer
        else{
            $_observers->offsetSet( $observer,Arrays::makeSet( $event ) );
        }
    }
    
    /**
     * @see http://php.net/splsubject.detach
     *
     * @param string $1                 name of event to detach from observer
     */
    public function detach( \SplSubject $observer ){
        static $_observers;
        if( ! ($this instanceof \SplObserver) ){ return; }
        if( ! $_observers ){ $_observers = $this->_observers(); }
        $event = func_get_arg( 1 )?: "*";
        $wild = substr( $event ) === ".";
        if( $wild ){
            $event = rtrim( $event,"." );
        }
        // existing observer
        if( $_observers->offsetExists( $observer ) ){
            $events = $_observers->offsetGet( $observer );
            // detach observer?
            if( $event === "*" ){
                $_observers->offsetUnset( $observer );
            }
            // or specific events?
            else{
                $events = $_observers->offsetGet( $observer );
                // remove specified event [and sub-named events]
                foreach( $events as $k ){
                    if( $k === $event || ($wild && strpos( $k,$event ) === 0) ){
                        $events->offsetUnset( $event );
                    }
                }
                // remove observer if no events remain
                if( $events->count() === 0 ){
                    $_observers->offsetUnset( $observer );
                }
            }
        }
    }
    
    /**
     * @see http://php.net/splsubject.notify
     *
     * @param string $0                 name of event to notify observers of
     */
    public function notify(){
        if( ! ($this instanceof \SplSubject) ){ return; }
        $event = func_get_arg( 0 )?: "*";
        foreach( $this->_observable_filterObservers( $event,$exact ) as $observer ){
            $observer->update( $this,$event );
        }
    }

    /**
     * finds observers subscribed to a given event.
     *
     * @param string $event             name of event to check
     * @return array                    list of observers subscribed to event
     */
    protected function _observable_filterObservers( $event ){
        static $_observers;
        if( ! $_observers ){ $_observers = $this->_observers(); }
        $list = [];
        foreach( $_observers as $o ){
            foreach( $_observers->offsetGet( $o ) as $k ){
                $wild = substr( $k,-1 ) === ".";
                if(
                    $event === "*"
                    || $k === $event
                    || $k === "*"
                    || ($wild && strpos( $k,$event ) === 0)
                ){
                    $list[] = $o; break;
                }
            }
        }
        return $list;
    }
    
    /**
     * singleton(ish) factory: [creates and] returns a container for observers.
     *
     * @return object                   list of observers of this subject.
     */
    protected function _observers(){
        static $_observers;
        if( ! $_observers ){ $_observers = new \SplObjectStorage; }
        return $_observers;
    }
}